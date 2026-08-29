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

function printfulAdminContext(PDO $pdo): array
{
    $config = PrintfulConfig::fromEnvironment(false);
    $vendor = $pdo->query("SELECT id, vendor_name FROM vendors WHERE fulfillment_provider = 'printful' OR LOWER(TRIM(vendor_name)) = 'printful' ORDER BY fulfillment_provider = 'printful' DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $products = $pdo->query("SELECT p.id, p.product_name, p.sku, p.vendor_id, p.is_apparel, p.apparel_size_type, vpm.external_product_id, vpm.external_product_name, vpm.mapping_status, COUNT(pv.id) AS variant_count, SUM(CASE WHEN vvm.id IS NOT NULL AND vvm.is_active = 1 THEN 1 ELSE 0 END) AS mapped_variant_count FROM products p LEFT JOIN vendor_product_mappings vpm ON vpm.product_id = p.id AND vpm.vendor_id = p.vendor_id LEFT JOIN product_variants pv ON pv.product_id = p.id AND pv.is_active = 1 LEFT JOIN vendor_variant_mappings vvm ON vvm.product_variant_id = pv.id AND vvm.vendor_product_mapping_id = vpm.id WHERE p.is_active = 1 GROUP BY p.id, p.product_name, p.sku, p.vendor_id, p.is_apparel, p.apparel_size_type, vpm.external_product_id, vpm.external_product_name, vpm.mapping_status ORDER BY p.product_name")->fetchAll(PDO::FETCH_ASSOC);
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
        $store = $client->store();
        $webhooks = null;
        try { $webhooks = $client->webhookConfiguration(); } catch (Throwable) { $webhooks = null; }
        printfulAdminJson(['success' => true, 'store' => $store['result'] ?? $store, 'webhooks' => $webhooks['result'] ?? null, 'auto_confirm' => $config->autoConfirm]);
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
    if ($method !== 'POST') {
        printfulAdminJson(['success' => false, 'message' => 'Unknown Printful action.'], 404);
    }

    printfulAdminRequireCsrf();
    if ($action === 'save_child_size') {
        $id = isset($_POST['id']) && $_POST['id'] !== '' ? printfulAdminPositiveId($_POST['id'], 'size') : 0;
        $name = trim((string) ($_POST['name'] ?? ''));
        $sort = max(0, (int) ($_POST['sort_order'] ?? 0));
        $active = isset($_POST['is_active']) ? 1 : 0;
        if ($name === '' || mb_strlen($name) > 50) throw new InvalidArgumentException('Enter a children\'s size name up to 50 characters.');
        if ($id) {
            $stmt = $pdo->prepare('UPDATE childrens_apparel_sizes SET name = :name, sort_order = :sort, is_active = :active WHERE id = :id');
            $stmt->execute([':name' => $name, ':sort' => $sort, ':active' => $active, ':id' => $id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO childrens_apparel_sizes (name, sort_order, is_active) VALUES (:name, :sort, :active)');
            $stmt->execute([':name' => $name, ':sort' => $sort, ':active' => $active]);
        }
        printfulAdminJson(['success' => true, 'message' => 'Children\'s size saved.']);
    }
    if ($action === 'save_mapping') {
        $productId = printfulAdminPositiveId($_POST['product_id'] ?? null, 'local product');
        $externalProductId = trim((string) ($_POST['external_product_id'] ?? ''));
        $sizeType = (string) ($_POST['size_type'] ?? '');
        $mappings = json_decode((string) ($_POST['mappings'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR);
        if ($externalProductId === '' || !in_array($sizeType, ['adult', 'children', 'none'], true) || !is_array($mappings) || $mappings === []) {
            throw new InvalidArgumentException('Select a Printful product, a size type, and at least one mapped size.');
        }
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
        $stmt = $pdo->prepare("UPDATE products SET vendor_id = :vendor_id, is_apparel = :is_apparel, apparel_size_type = :size_type WHERE id = :id AND is_active = 1");
        $stmt->execute([':vendor_id' => $vendorId, ':is_apparel' => $sizeType === 'none' ? 0 : 1, ':size_type' => $sizeType === 'none' ? null : $sizeType, ':id' => $productId]);
        if ($stmt->rowCount() === 0) {
            $check = $pdo->prepare('SELECT COUNT(*) FROM products WHERE id = :id AND is_active = 1'); $check->execute([':id' => $productId]);
            if (!(int) $check->fetchColumn()) throw new InvalidArgumentException('Local product not found.');
        }
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
