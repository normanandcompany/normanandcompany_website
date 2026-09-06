<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../classes/Fulfillment/bootstrap.php';

$options = getopt('', ['product:', 'user:']);
$productId = (int) ($options['product'] ?? 0);
$userId = (int) ($options['user'] ?? 0);
$config = PrintfulConfig::fromEnvironment();
$allowOverride = in_array(strtolower((string) normanEnv('PRINTFUL_DEV_ALLOW_PAYMENT_OVERRIDE', 'false')), ['1', 'true', 'yes', 'on'], true);

if (!$config->isDevelopment() || $config->autoConfirm || !$allowOverride) {
    fwrite(STDERR, "Refusing to run: use a development environment with auto-confirm disabled and the development payment override explicitly enabled.\n");
    exit(1);
}
if ($productId <= 0 || $userId <= 0) {
    fwrite(STDERR, "Usage: php tests/printful_e2e.php --product=7 --user=1\n");
    exit(1);
}

$pdo = normanCreateDatabaseConnection('admin');
$client = new PrintfulClient($config, $pdo);
$checkout = new CheckoutService($pdo, $client, $config);
$coordinator = new FulfillmentCoordinator($pdo, $client, $config);
$webhooks = new PrintfulWebhookService($pdo, $client, $coordinator);
$checks = [];

$assert = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks[] = $message;
};
$expectInvalid = static function (callable $callback, string $message) use ($assert): void {
    try {
        $callback();
    } catch (InvalidArgumentException) {
        $assert(true, $message);
        return;
    }
    throw new RuntimeException($message . ' (request was unexpectedly accepted)');
};

$variantStmt = $pdo->prepare("SELECT pv.id FROM product_variants pv INNER JOIN vendor_variant_mappings vvm ON vvm.product_variant_id = pv.id AND vvm.is_active = 1 INNER JOIN vendor_product_mappings vpm ON vpm.id = vvm.vendor_product_mapping_id AND vpm.mapping_status = 'active' INNER JOIN vendors v ON v.id = vpm.vendor_id AND v.fulfillment_provider = 'printful' WHERE pv.product_id = :product_id AND pv.is_active = 1 AND vvm.external_catalog_variant_id IS NOT NULL ORDER BY pv.sort_order, pv.id LIMIT 1");
$variantStmt->execute([':product_id' => $productId]);
$productVariantId = (int) $variantStmt->fetchColumn();
$assert($productVariantId > 0, 'Mapped Printful product has a shipping-ready local variant.');

$cart = [['product_id' => $productId, 'product_variant_id' => $productVariantId, 'quantity' => 1]];
$usAddress = [
    'name' => 'Deployment Test', 'email' => 'deployment-test@example.com', 'phone' => '3125550100',
    'address1' => '1 N State St', 'address2' => '', 'city' => 'Chicago', 'state_code' => 'IL',
    'country_code' => 'US', 'postal_code' => '60602'
];
$alternateAddress = [...$usAddress, 'address1' => '233 S Wacker Dr', 'postal_code' => '60606'];
$caAddress = [
    'name' => 'Deployment Test', 'email' => 'deployment-test@example.com', 'phone' => '4165550100',
    'address1' => '100 Queen St W', 'address2' => '', 'city' => 'Toronto', 'state_code' => 'ON',
    'country_code' => 'CA', 'postal_code' => 'M5H 2N2'
];

$usQuote = $checkout->quote($userId, $cart, $usAddress);
$assert(!empty($usQuote['rates']), 'Live US shipping rates are returned through CheckoutService.');
$caQuote = $checkout->quote($userId, $cart, $caAddress);
$assert(!empty($caQuote['rates']), 'Live Canadian shipping rates are returned through CheckoutService.');

$addressQuote = $checkout->quote($userId, $cart, $usAddress);
$expectInvalid(fn() => $checkout->prepareOrder($userId, $cart, $alternateAddress, (string) $addressQuote['quote_token'], (string) $addressQuote['rates'][0]['id']), 'Changing the address invalidates a shipping quote.');

$quantityQuote = $checkout->quote($userId, $cart, $usAddress);
$changedCart = [...$cart];
$changedCart[0]['quantity'] = 2;
$expectInvalid(fn() => $checkout->prepareOrder($userId, $changedCart, $usAddress, (string) $quantityQuote['quote_token'], (string) $quantityQuote['rates'][0]['id']), 'Changing quantity invalidates a shipping quote.');

$expiredQuote = $checkout->quote($userId, $cart, $usAddress);
$expireStmt = $pdo->prepare('UPDATE checkout_shipping_quotes SET expires_at = DATE_SUB(NOW(), INTERVAL 1 SECOND) WHERE quote_token = :token');
$expireStmt->execute([':token' => $expiredQuote['quote_token']]);
$expectInvalid(fn() => $checkout->prepareOrder($userId, $cart, $usAddress, (string) $expiredQuote['quote_token'], (string) $expiredQuote['rates'][0]['id']), 'Expired shipping quotes are rejected.');

$otherProductStmt = $pdo->prepare("SELECT p.id FROM products p INNER JOIN vendors v ON v.id = p.vendor_id WHERE p.id <> :product_id AND p.visible = 1 AND p.is_active = 1 AND v.visible = 1 AND v.is_active = 1 AND COALESCE(v.fulfillment_provider, '') <> 'printful' AND p.inventory_count > 0 ORDER BY p.id LIMIT 1");
$otherProductStmt->execute([':product_id' => $productId]);
$otherProduct = $otherProductStmt->fetchColumn();
$assert((int) $otherProduct > 0, 'A non-Printful product is available for mixed-vendor testing.');
$mixedQuote = $checkout->quote($userId, [...$cart, ['product_id' => (int) $otherProduct, 'quantity' => 1]], $usAddress);
$assert(!empty($mixedQuote['rates']), 'Mixed-vendor cart returns rates for its Printful group.');

$availability = $pdo->prepare("UPDATE vendor_variant_mappings SET availability_status = 'out_of_stock' WHERE product_variant_id = :variant_id");
$pdo->beginTransaction();
try {
    $availability->execute([':variant_id' => $productVariantId]);
    $expectInvalid(fn() => $checkout->quote($userId, $cart, $usAddress), 'Unavailable Printful variants are rejected server-side.');
    $pdo->rollBack();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $exception;
}

$orderQuote = $checkout->quote($userId, $cart, $usAddress);
$prepared = $checkout->prepareOrder($userId, $cart, $usAddress, (string) $orderQuote['quote_token'], (string) $orderQuote['rates'][0]['id']);
$orderId = (int) $prepared['order_id'];
$assert($orderId > 0 && $prepared['payment_status'] === 'unpaid', 'Checkout prepares an unpaid local order without starting fulfillment.');
$expectInvalid(fn() => $checkout->prepareOrder($userId, $cart, $usAddress, (string) $orderQuote['quote_token'], (string) $orderQuote['rates'][0]['id']), 'A shipping quote cannot create a second order.');

try {
    $coordinator->processPaidOrder($orderId);
    throw new RuntimeException('Unpaid order unexpectedly reached fulfillment.');
} catch (RuntimeException $exception) {
    if (!str_contains($exception->getMessage(), 'not paid')) throw $exception;
    $assert(true, 'The fulfillment coordinator blocks unpaid orders.');
}

$reference = 'DEV-E2E-' . $orderId;
$pdo->beginTransaction();
try {
    $paid = $pdo->prepare("UPDATE orders SET payment_status = 'paid', payment_provider = 'development_override', payment_reference = :reference, payment_amount = total_amount, payment_currency = currency_code, paid_at = NOW(), order_status = 'paid' WHERE id = :id AND payment_status = 'unpaid'");
    $paid->execute([':reference' => $reference, ':id' => $orderId]);
    if ($paid->rowCount() !== 1) throw new RuntimeException('Unable to mark the development test order paid.');
    $transaction = $pdo->prepare("INSERT INTO transactions (order_id, transaction_reference, payment_provider, transaction_type, currency_code, transaction_amount, product_sales_amount, shipping_amount, sales_tax_amount, transaction_status, processed_at, visible) SELECT id, :reference, 'development_override', 'sale', currency_code, total_amount, subtotal_amount, shipping_amount, tax_amount, 'paid', NOW(), 1 FROM orders WHERE id = :id");
    $transaction->execute([':reference' => $reference, ':id' => $orderId]);
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $exception;
}

$firstFulfillment = $coordinator->processPaidOrder($orderId);
$printfulResult = array_values(array_filter($firstFulfillment['fulfillments'], static fn(array $item): bool => $item['provider'] === 'printful'))[0] ?? null;
$externalOrderId = (string) ($printfulResult['external_order_id'] ?? '');
$assert($externalOrderId !== '' && ($printfulResult['auto_confirmed'] ?? true) === false, 'Paid development order creates an unconfirmed Printful draft.');

$remoteOrder = $client->order($externalOrderId)['result'] ?? [];
$assert(is_array($remoteOrder) && strtolower((string) ($remoteOrder['status'] ?? '')) === 'draft', 'Printful confirms the external order remains in draft status.');

$retry = $coordinator->processPaidOrder($orderId);
$retryResult = array_values(array_filter($retry['fulfillments'], static fn(array $item): bool => $item['provider'] === 'printful'))[0] ?? null;
$assert(($retryResult['idempotent'] ?? false) === true && (string) ($retryResult['external_order_id'] ?? '') === $externalOrderId, 'Retry reuses the same Printful order instead of creating duplicate merchandise.');

$webhookBody = json_encode(['type' => 'order_updated', 'data' => ['order' => ['id' => $externalOrderId]]], JSON_THROW_ON_ERROR);
$firstWebhook = $webhooks->handle($webhookBody);
$duplicateWebhook = $webhooks->handle($webhookBody);
$assert(($firstWebhook['duplicate'] ?? true) === false && ($duplicateWebhook['duplicate'] ?? false) === true, 'Webhook replay is idempotent and re-fetches authoritative Printful state once.');

$remoteItemId = (string) ($remoteOrder['items'][0]['id'] ?? '');
$assert($remoteItemId !== '', 'Printful draft contains the mapped order line item.');
$fulfillmentIdStmt = $pdo->prepare('SELECT id FROM vendor_fulfillments WHERE order_id = :order_id AND external_order_id = :external_id');
$fulfillmentIdStmt->execute([':order_id' => $orderId, ':external_id' => $externalOrderId]);
$fulfillmentId = (int) $fulfillmentIdStmt->fetchColumn();
$pdo->beginTransaction();
try {
    $synthetic = $remoteOrder;
    $synthetic['status'] = 'inprocess';
    $synthetic['shipments'] = [
        ['id' => 'E2E-PACKAGE-1', 'tracking_number' => 'E2E-TRACK-1', 'tracking_url' => 'https://example.com/track/1', 'carrier' => 'Test Carrier', 'ship_date' => gmdate('c'), 'items' => [['item_id' => $remoteItemId, 'quantity' => 1]]],
        ['id' => 'E2E-PACKAGE-2', 'tracking_number' => 'E2E-TRACK-2', 'tracking_url' => 'https://example.com/track/2', 'carrier' => 'Test Carrier', 'ship_date' => gmdate('c'), 'items' => [['item_id' => $remoteItemId, 'quantity' => 1]]]
    ];
    $coordinator->synchronizePrintfulOrder($synthetic);
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM fulfillment_shipments WHERE vendor_fulfillment_id = :id');
    $countStmt->execute([':id' => $fulfillmentId]);
    $assert((int) $countStmt->fetchColumn() === 2, 'Multiple shipment packages and tracking records synchronize correctly.');
    $pdo->rollBack();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $exception;
}

echo json_encode([
    'success' => true,
    'order_id' => $orderId,
    'order_number' => $prepared['order_number'],
    'printful_order_id' => $externalOrderId,
    'printful_status' => $remoteOrder['status'] ?? null,
    'us_rate_count' => count($usQuote['rates']),
    'ca_rate_count' => count($caQuote['rates']),
    'checks' => $checks
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
