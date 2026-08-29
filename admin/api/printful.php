<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../../classes/Fulfillment/bootstrap.php';

function printfulAdminJson(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function printfulAdminCsrfToken(): string
{
    if (empty($_SESSION['printful_admin_csrf'])) {
        $_SESSION['printful_admin_csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['printful_admin_csrf'];
}

function printfulAdminRequireCsrf(): void
{
    $provided = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');
    if ($provided === '' || !hash_equals(printfulAdminCsrfToken(), $provided)) {
        printfulAdminJson(['success' => false, 'message' => 'Invalid administrator session token.'], 403);
    }
}

function printfulAdminPositiveId(mixed $value, string $label): int
{
    if (!is_scalar($value) || !ctype_digit((string) $value) || (int) $value <= 0) {
        throw new InvalidArgumentException("Select a valid {$label}.");
    }
    return (int) $value;
}

function printfulAdminPricingPreview(PDO $pdo, int $productId, string $mode): array
{
    $stmt = $pdo->prepare("SELECT p.id, p.product_name, p.price, p.is_apparel, vpm.id AS mapping_id, vpm.external_product_id, vpm.pricing_mode, vpm.pricing_synced_at FROM products p INNER JOIN vendors v ON v.id = p.vendor_id AND v.fulfillment_provider = 'printful' INNER JOIN vendor_product_mappings vpm ON vpm.product_id = p.id AND vpm.vendor_id = p.vendor_id AND vpm.mapping_status = 'active' WHERE p.id = :id AND p.is_active = 1 LIMIT 1");
    $stmt->execute([':id' => $productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) throw new InvalidArgumentException('Save a Printful product mapping before syncing prices.');
    if (!(int) $product['is_apparel']) throw new InvalidArgumentException('Variant pricing is available for sized apparel products.');

    $response = normanPrintfulClient($pdo)->syncProduct((string) $product['external_product_id']);
    $remote = $response['result'] ?? null;
    if (!is_array($remote)) throw new RuntimeException('Printful did not return the mapped product.');
    $remoteVariants = is_array($remote['sync_variants'] ?? null) ? $remote['sync_variants'] : (is_array($remote['variants'] ?? null) ? $remote['variants'] : []);
    $remoteById = [];
    foreach ($remoteVariants as $variant) {
        $remoteById[(string) ($variant['id'] ?? '')] = $variant;
    }

    $variantStmt = $pdo->prepare("SELECT pv.id AS local_variant_id, pv.size_label_snapshot AS size_label, COALESCE(pv.price_adjustment, 0) AS current_adjustment, p.price + COALESCE(pv.price_adjustment, 0) AS current_price, vvm.external_variant_id, vvm.external_variant_name FROM product_variants pv INNER JOIN products p ON p.id = pv.product_id INNER JOIN vendor_variant_mappings vvm ON vvm.product_variant_id = pv.id INNER JOIN vendor_product_mappings vpm ON vpm.id = vvm.vendor_product_mapping_id AND vpm.product_id = p.id WHERE pv.product_id = :product_id AND pv.is_active = 1 AND vvm.is_active = 1 ORDER BY pv.sort_order, pv.id");
    $variantStmt->execute([':product_id' => $productId]);
    $variants = [];
    $currencies = [];
    foreach ($variantStmt->fetchAll(PDO::FETCH_ASSOC) as $localVariant) {
        $remoteVariant = $remoteById[(string) $localVariant['external_variant_id']] ?? null;
        if (!is_array($remoteVariant) || !is_numeric($remoteVariant['retail_price'] ?? null)) {
            throw new InvalidArgumentException('Printful did not provide a retail price for ' . $localVariant['size_label'] . '.');
        }
        $currency = strtoupper((string) ($remoteVariant['currency'] ?? 'USD'));
        $currencies[$currency] = true;
        $variants[] = [
            ...$localVariant,
            'remote_price' => $remoteVariant['retail_price'],
            'currency' => $currency
        ];
    }
    if ($currencies !== [] && array_keys($currencies) !== ['USD']) {
        throw new InvalidArgumentException('Printful variant pricing must be returned in USD before it can be synchronized.');
    }

    $preview = PrintfulVariantPricing::calculate((float) $product['price'], $variants, $mode);
    return [
        ...$preview,
        'product_id' => (int) $product['id'],
        'product_name' => $product['product_name'],
        'previous_mode' => $product['pricing_mode'],
        'previously_synced_at' => $product['pricing_synced_at']
    ];
}

function printfulAdminContext(PDO $pdo): array
{
    $config = PrintfulConfig::fromEnvironment(false);
    $vendor = $pdo->query("SELECT id, vendor_name FROM vendors WHERE fulfillment_provider = 'printful' OR LOWER(TRIM(vendor_name)) = 'printful' ORDER BY fulfillment_provider = 'printful' DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $products = $pdo->query("SELECT p.id, p.product_name, p.sku, p.price, p.product_category_id, p.vendor_id, p.is_apparel, p.apparel_size_type, vpm.external_product_id, vpm.external_product_name, vpm.mapping_status, vpm.pricing_mode, vpm.pricing_synced_at, COUNT(pv.id) AS variant_count, SUM(CASE WHEN vvm.id IS NOT NULL AND vvm.is_active = 1 THEN 1 ELSE 0 END) AS mapped_variant_count FROM products p LEFT JOIN vendor_product_mappings vpm ON vpm.product_id = p.id AND vpm.vendor_id = p.vendor_id LEFT JOIN product_variants pv ON pv.product_id = p.id AND pv.is_active = 1 LEFT JOIN vendor_variant_mappings vvm ON vvm.product_variant_id = pv.id AND vvm.vendor_product_mapping_id = vpm.id WHERE p.is_active = 1 GROUP BY p.id, p.product_name, p.sku, p.price, p.product_category_id, p.vendor_id, p.is_apparel, p.apparel_size_type, vpm.external_product_id, vpm.external_product_name, vpm.mapping_status, vpm.pricing_mode, vpm.pricing_synced_at ORDER BY p.product_name")->fetchAll(PDO::FETCH_ASSOC);
    $adult = $pdo->query("SELECT id, name, is_active, sort_order FROM apparel_sizes WHERE name NOT LIKE 'Child-%' ORDER BY sort_order, id")->fetchAll(PDO::FETCH_ASSOC);
    $children = $pdo->query('SELECT id, name, is_active, sort_order FROM childrens_apparel_sizes ORDER BY sort_order, id')->fetchAll(PDO::FETCH_ASSOC);
    $fulfillments = $pdo->query("SELECT vf.id, vf.order_id, o.order_number, o.payment_status, o.fulfillment_status AS order_fulfillment_status, v.vendor_name, vf.external_order_id, vf.fulfillment_status, vf.vendor_order_status, vf.shipping_service_name, vf.last_synced_at, vf.last_error_code, vf.last_error_message, vf.retry_count FROM vendor_fulfillments vf INNER JOIN orders o ON o.id = vf.order_id INNER JOIN vendors v ON v.id = vf.vendor_id ORDER BY vf.created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    $health = $pdo->query("SELECT * FROM fulfillment_api_health WHERE provider = 'printful'")->fetch(PDO::FETCH_ASSOC) ?: null;

    return [
        'success' => true,
        'csrf_token' => printfulAdminCsrfToken(),
        'configuration' => [
            'token_configured' => $config->token !== '',
            'store_id_configured' => $config->storeId !== null,
            'auto_confirm' => $config->autoConfirm,
            'app_environment' => $config->appEnvironment,
            'webhook_secret_configured' => $config->webhookSecret !== ''
        ],
        'vendor' => $vendor ?: null,
        'products' => $products,
        'adult_sizes' => $adult,
        'childrens_sizes' => $children,
        'fulfillments' => $fulfillments,
        'health' => $health
    ];
}

try {
    $action = (string) ($_GET['action'] ?? $_POST['action'] ?? 'context');
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET' && $action === 'context') {
        printfulAdminJson(printfulAdminContext($pdo));
    }
    if ($method === 'GET' && $action === 'diagnostics') {
        $config = PrintfulConfig::fromEnvironment();
        $client = new PrintfulClient($config, $pdo);
        $products = $client->listSyncProducts();
        $webhooks = null;
        try { $webhooks = $client->webhookConfiguration(); } catch (Throwable) { $webhooks = null; }
        printfulAdminJson([
            'success' => true,
            'store_access' => $config->storeId === null ? 'Authorized store' : 'Configured store',
            'sync_product_count' => count($products),
            'webhooks' => $webhooks['result'] ?? null,
            'auto_confirm' => $config->autoConfirm
        ]);
    }
    if ($method === 'GET' && $action === 'sync_products') {
        $client = normanPrintfulClient($pdo);
        printfulAdminJson(['success' => true, 'products' => $client->listSyncProducts()]);
    }
    if ($method === 'GET' && $action === 'sync_product') {
        $id = trim((string) ($_GET['id'] ?? ''));
        if ($id === '') throw new InvalidArgumentException('Select a Printful product.');
        $response = normanPrintfulClient($pdo)->syncProduct($id);
        printfulAdminJson(['success' => true, 'product' => $response['result'] ?? null]);
    }
    if ($method === 'GET' && $action === 'pricing_preview') {
        $productId = printfulAdminPositiveId($_GET['product_id'] ?? null, 'local product');
        $mode = (string) ($_GET['mode'] ?? PrintfulVariantPricing::MODE_DIFFERENCE);
        printfulAdminJson(['success' => true, 'preview' => printfulAdminPricingPreview($pdo, $productId, $mode)]);
    }
    if ($method !== 'POST') {
        printfulAdminJson(['success' => false, 'message' => 'Unknown Printful action.'], 404);
    }

    printfulAdminRequireCsrf();
    if ($action === 'apply_pricing') {
        $productId = printfulAdminPositiveId($_POST['product_id'] ?? null, 'local product');
        $mode = (string) ($_POST['mode'] ?? PrintfulVariantPricing::MODE_DIFFERENCE);
        $preview = printfulAdminPricingPreview($pdo, $productId, $mode);
        $pdo->beginTransaction();
        $update = $pdo->prepare('UPDATE product_variants SET price_adjustment = :adjustment, printful_retail_price = :remote_price, printful_currency = :currency, printful_price_synced_at = NOW() WHERE id = :id AND product_id = :product_id');
        foreach ($preview['variants'] as $variant) {
            $update->execute([
                ':adjustment' => $variant['price_adjustment'],
                ':remote_price' => $variant['remote_price'],
                ':currency' => $variant['currency'],
                ':id' => $variant['local_variant_id'],
                ':product_id' => $productId
            ]);
            if ($update->rowCount() === 0) {
                $check = $pdo->prepare('SELECT COUNT(*) FROM product_variants WHERE id = :id AND product_id = :product_id');
                $check->execute([':id' => $variant['local_variant_id'], ':product_id' => $productId]);
                if (!(int) $check->fetchColumn()) throw new RuntimeException('A product variant changed while pricing was being applied.');
            }
        }
        $mapping = $pdo->prepare('UPDATE vendor_product_mappings SET pricing_mode = :mode, pricing_synced_at = NOW() WHERE product_id = :product_id');
        $mapping->execute([':mode' => $mode, ':product_id' => $productId]);
        $pdo->commit();
        printfulAdminJson(['success' => true, 'message' => 'Variant pricing synchronized from Printful.', 'preview' => $preview]);
    }
    if ($action === 'save_size') {
        $sizeType = (string) ($_POST['size_type'] ?? '');
        if (!in_array($sizeType, ['adult', 'children'], true)) throw new InvalidArgumentException('Select an adult or children\'s size catalog.');
        $sizeTable = $sizeType === 'adult' ? 'apparel_sizes' : 'childrens_apparel_sizes';
        $sizeLabel = $sizeType === 'adult' ? 'Adult' : 'Children\'s';
        $id = isset($_POST['id']) && $_POST['id'] !== '' ? printfulAdminPositiveId($_POST['id'], 'size') : 0;
        $name = trim((string) ($_POST['name'] ?? ''));
        $sort = max(0, (int) ($_POST['sort_order'] ?? 0));
        $active = isset($_POST['is_active']) ? 1 : 0;
        if ($name === '' || mb_strlen($name) > 50) throw new InvalidArgumentException("Enter a {$sizeLabel} size name up to 50 characters.");
        $duplicate = $pdo->prepare("SELECT id FROM {$sizeTable} WHERE LOWER(TRIM(name)) = LOWER(TRIM(:name)) AND id <> :id LIMIT 1");
        $duplicate->execute([':name' => $name, ':id' => $id]);
        if ($duplicate->fetchColumn()) throw new InvalidArgumentException("That {$sizeLabel} size already exists.");
        if ($id) {
            $stmt = $pdo->prepare("UPDATE {$sizeTable} SET name = :name, sort_order = :sort, is_active = :active WHERE id = :id");
            $stmt->execute([':name' => $name, ':sort' => $sort, ':active' => $active, ':id' => $id]);
            if ($stmt->rowCount() === 0) {
                $check = $pdo->prepare("SELECT COUNT(*) FROM {$sizeTable} WHERE id = :id");
                $check->execute([':id' => $id]);
                if (!(int) $check->fetchColumn()) throw new InvalidArgumentException('Size not found.');
            }
        } else {
            $stmt = $pdo->prepare("INSERT INTO {$sizeTable} (name, sort_order, is_active) VALUES (:name, :sort, :active)");
            $stmt->execute([':name' => $name, ':sort' => $sort, ':active' => $active]);
        }
        printfulAdminJson(['success' => true, 'message' => "{$sizeLabel} size saved."]);
    }
    if ($action === 'save_mapping') {
        $productId = printfulAdminPositiveId($_POST['product_id'] ?? null, 'local product');
        $externalProductId = trim((string) ($_POST['external_product_id'] ?? ''));
        $mappings = json_decode((string) ($_POST['mappings'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR);
        if ($externalProductId === '' || !is_array($mappings) || $mappings === []) {
            throw new InvalidArgumentException('Select a Printful product and at least one fulfillment variant.');
        }
        $productStmt = $pdo->prepare('SELECT is_apparel, apparel_size_type, product_category_id FROM products WHERE id = :id AND is_active = 1');
        $productStmt->execute([':id' => $productId]);
        $localProduct = $productStmt->fetch(PDO::FETCH_ASSOC);
        if (!$localProduct) throw new InvalidArgumentException('Local product not found.');
        $sizeType = !(int) $localProduct['is_apparel']
            ? 'none'
            : ((string) ($localProduct['apparel_size_type'] ?? '') ?: ((int) $localProduct['product_category_id'] === 7 ? 'children' : 'adult'));
        if (!in_array($sizeType, ['adult', 'children', 'none'], true)) throw new InvalidArgumentException('The product has an invalid apparel size catalog.');
        $vendor = $pdo->query("SELECT id FROM vendors WHERE fulfillment_provider = 'printful' OR LOWER(TRIM(vendor_name)) = 'printful' ORDER BY fulfillment_provider = 'printful' DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$vendor) throw new RuntimeException('The Printful vendor record is missing.');
        $vendorId = (int) $vendor['id'];
        $remote = normanPrintfulClient($pdo)->syncProduct($externalProductId)['result'] ?? null;
        if (!is_array($remote)) throw new RuntimeException('Printful did not return the selected product.');
        $remoteProduct = is_array($remote['sync_product'] ?? null) ? $remote['sync_product'] : $remote;
        $remoteVariants = is_array($remote['sync_variants'] ?? null) ? $remote['sync_variants'] : (is_array($remote['variants'] ?? null) ? $remote['variants'] : []);
        $remoteById = [];
        foreach ($remoteVariants as $variant) $remoteById[(string) ($variant['id'] ?? '')] = $variant;

        $pdo->beginTransaction();
        $stmt = $pdo->prepare('UPDATE products SET vendor_id = :vendor_id WHERE id = :id');
        $stmt->execute([':vendor_id' => $vendorId, ':id' => $productId]);
        $stmt = $pdo->prepare("INSERT INTO vendor_product_mappings (product_id, vendor_id, external_product_id, external_product_name, external_data_json, mapping_status, last_synced_at) VALUES (:product_id, :vendor_id, :external_id, :external_name, :data, 'active', NOW()) ON DUPLICATE KEY UPDATE external_product_id = VALUES(external_product_id), external_product_name = VALUES(external_product_name), external_data_json = VALUES(external_data_json), mapping_status = 'active', last_synced_at = NOW(), id = LAST_INSERT_ID(id)");
        $stmt->execute([':product_id' => $productId, ':vendor_id' => $vendorId, ':external_id' => $externalProductId, ':external_name' => $remoteProduct['name'] ?? null, ':data' => json_encode($remoteProduct, JSON_THROW_ON_ERROR)]);
        $mappingId = (int) $pdo->lastInsertId();
        if (!$mappingId) { $find = $pdo->prepare('SELECT id FROM vendor_product_mappings WHERE product_id = :product_id AND vendor_id = :vendor_id'); $find->execute([':product_id' => $productId, ':vendor_id' => $vendorId]); $mappingId = (int) $find->fetchColumn(); }

        $pdo->prepare('UPDATE product_variants SET is_active = 0 WHERE product_id = :product_id')->execute([':product_id' => $productId]);

        if ($sizeType === 'none') {
            $externalVariantId = trim((string) ($mappings[0]['external_variant_id'] ?? ''));
            if ($externalVariantId === '' || !isset($remoteById[$externalVariantId])) throw new InvalidArgumentException('Select one Printful Sync Variant for the standard product.');
            $variant = $remoteById[$externalVariantId];
            $stmt = $pdo->prepare('UPDATE vendor_product_mappings SET default_external_variant_id = :external_id, default_external_variant_name = :name, default_external_sku = :sku, default_availability_status = :availability WHERE id = :id');
            $stmt->execute([':external_id' => $externalVariantId, ':name' => $variant['name'] ?? $variant['product']['name'] ?? null, ':sku' => $variant['sku'] ?? null, ':availability' => $variant['availability_status'] ?? (!empty($variant['synced']) ? 'active' : 'unsynced'), ':id' => $mappingId]);
            $pdo->commit();
            printfulAdminJson(['success' => true, 'message' => 'Printful standard product mapping saved.']);
        }

        $pdo->prepare('UPDATE vendor_product_mappings SET default_external_variant_id = NULL, default_external_variant_name = NULL, default_external_sku = NULL, default_availability_status = NULL WHERE id = :id')->execute([':id' => $mappingId]);

        $sizeTable = $sizeType === 'adult' ? 'apparel_sizes' : 'childrens_apparel_sizes';
        $sizeColumn = $sizeType === 'adult' ? 'adult_size_id' : 'childrens_size_id';
        $otherColumn = $sizeType === 'adult' ? 'childrens_size_id' : 'adult_size_id';
        $selectedVariantIds = [];
        foreach ($mappings as $mapping) {
            $sizeId = printfulAdminPositiveId($mapping['size_id'] ?? null, 'local size');
            $externalVariantId = trim((string) ($mapping['external_variant_id'] ?? ''));
            if ($externalVariantId === '' || !isset($remoteById[$externalVariantId])) throw new InvalidArgumentException('Every selected local size must map to a variant from the selected Printful product.');
            $sizeStmt = $pdo->prepare("SELECT name, sort_order FROM {$sizeTable} WHERE id = :id AND is_active = 1");
            $sizeStmt->execute([':id' => $sizeId]); $size = $sizeStmt->fetch(PDO::FETCH_ASSOC);
            if (!$size) throw new InvalidArgumentException('A selected local size is inactive or missing.');
            $stmt = $pdo->prepare("INSERT INTO product_variants (product_id, {$sizeColumn}, {$otherColumn}, size_label_snapshot, is_active, sort_order) VALUES (:product_id, :size_id, NULL, :label, 1, :sort) ON DUPLICATE KEY UPDATE size_label_snapshot = VALUES(size_label_snapshot), is_active = 1, sort_order = VALUES(sort_order), id = LAST_INSERT_ID(id)");
            $stmt->execute([':product_id' => $productId, ':size_id' => $sizeId, ':label' => $size['name'], ':sort' => $size['sort_order']]);
            $localVariantId = (int) $pdo->lastInsertId();
            if (!$localVariantId) { $find = $pdo->prepare("SELECT id FROM product_variants WHERE product_id = :product_id AND {$sizeColumn} = :size_id"); $find->execute([':product_id' => $productId, ':size_id' => $sizeId]); $localVariantId = (int) $find->fetchColumn(); }
            $variant = $remoteById[$externalVariantId];
            $variantName = (string) ($variant['name'] ?? $variant['product']['name'] ?? '');
            $existingExternal = $pdo->prepare('SELECT external_variant_id FROM vendor_variant_mappings WHERE product_variant_id = :variant_id AND vendor_product_mapping_id = :mapping_id');
            $existingExternal->execute([':variant_id' => $localVariantId, ':mapping_id' => $mappingId]);
            $previousExternalVariantId = $existingExternal->fetchColumn();
            if ($previousExternalVariantId !== false && (string) $previousExternalVariantId !== $externalVariantId) {
                $pdo->prepare('UPDATE product_variants SET price_adjustment = 0.00 WHERE id = :id')->execute([':id' => $localVariantId]);
            }
            if (is_numeric($variant['retail_price'] ?? null)) {
                $priceStmt = $pdo->prepare('UPDATE product_variants SET printful_retail_price = :price, printful_currency = :currency, printful_price_synced_at = NOW() WHERE id = :id');
                $priceStmt->execute([':price' => number_format((float) $variant['retail_price'], 2, '.', ''), ':currency' => strtoupper((string) ($variant['currency'] ?? 'USD')), ':id' => $localVariantId]);
            }
            $stmt = $pdo->prepare("INSERT INTO vendor_variant_mappings (product_variant_id, vendor_product_mapping_id, external_variant_id, external_variant_name, external_sku, external_color, external_size, availability_status, external_data_json, is_active, last_synced_at) VALUES (:variant_id, :mapping_id, :external_id, :name, :sku, :color, :size, :availability, :data, 1, NOW()) ON DUPLICATE KEY UPDATE external_variant_id = VALUES(external_variant_id), external_variant_name = VALUES(external_variant_name), external_sku = VALUES(external_sku), external_color = VALUES(external_color), external_size = VALUES(external_size), availability_status = VALUES(availability_status), external_data_json = VALUES(external_data_json), is_active = 1, last_synced_at = NOW()");
            $stmt->execute([':variant_id' => $localVariantId, ':mapping_id' => $mappingId, ':external_id' => $externalVariantId, ':name' => $variantName, ':sku' => $variant['sku'] ?? null, ':color' => $mapping['color'] ?? null, ':size' => $mapping['size'] ?? $size['name'], ':availability' => $variant['availability_status'] ?? ($variant['synced'] ?? true ? 'active' : 'unsynced'), ':data' => json_encode($variant, JSON_THROW_ON_ERROR)]);
            $selectedVariantIds[] = $localVariantId;
        }
        $pdo->commit();
        printfulAdminJson(['success' => true, 'message' => 'Printful product and size mappings saved.']);
    }
    if ($action === 'retry_fulfillment') {
        $orderId = printfulAdminPositiveId($_POST['order_id'] ?? null, 'order');
        printfulAdminJson(['success' => true, 'result' => startOrderFulfillment($pdo, $orderId), 'message' => 'Fulfillment processing completed.']);
    }
    if ($action === 'sync_fulfillment') {
        $fulfillmentId = printfulAdminPositiveId($_POST['fulfillment_id'] ?? null, 'fulfillment');
        $stmt = $pdo->prepare("SELECT vf.external_order_id FROM vendor_fulfillments vf INNER JOIN vendors v ON v.id = vf.vendor_id WHERE vf.id = :id AND v.fulfillment_provider = 'printful'");
        $stmt->execute([':id' => $fulfillmentId]); $externalId = $stmt->fetchColumn();
        if (!$externalId) throw new InvalidArgumentException('This fulfillment has no Printful order to synchronize.');
        $config = PrintfulConfig::fromEnvironment(); $client = new PrintfulClient($config, $pdo); $response = $client->order((string) $externalId);
        (new FulfillmentCoordinator($pdo, $client, $config))->synchronizePrintfulOrder((array) ($response['result'] ?? []));
        printfulAdminJson(['success' => true, 'message' => 'Printful order synchronized.']);
    }
    throw new InvalidArgumentException('Unknown Printful action.');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $status = $exception instanceof InvalidArgumentException ? 422 : 500;
    if ($exception instanceof PrintfulException) $status = in_array($exception->httpStatus(), [401, 403, 404, 429], true) ? (int) $exception->httpStatus() : 503;
    if ($status >= 500) error_log('Printful admin action failed: ' . $exception->getMessage());
    printfulAdminJson(['success' => false, 'message' => $status >= 500 ? 'The Printful action could not be completed.' : $exception->getMessage(), 'error_category' => $exception instanceof PrintfulException ? $exception->category() : null], $status);
}
