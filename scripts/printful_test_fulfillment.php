<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../classes/Fulfillment/bootstrap.php';

$options = getopt('', ['order:', 'mark-paid']);
$orderId = isset($options['order']) ? (int) $options['order'] : 0;
$config = PrintfulConfig::fromEnvironment();
$allowOverride = in_array(strtolower((string) normanEnv('PRINTFUL_DEV_ALLOW_PAYMENT_OVERRIDE', 'false')), ['1', 'true', 'yes', 'on'], true);

if (!$config->isDevelopment()) {
    fwrite(STDERR, "Refusing to run: APP_ENV must explicitly be development, local, or testing.\n");
    exit(1);
}
if ($orderId <= 0) {
    fwrite(STDERR, "Usage: php scripts/printful_test_fulfillment.php --order=123 [--mark-paid]\n");
    exit(1);
}
if ($config->autoConfirm) {
    fwrite(STDERR, "Refusing to run while PRINTFUL_AUTO_CONFIRM is enabled. Development testing must create drafts only.\n");
    exit(1);
}

$pdo = normanCreateDatabaseConnection('admin');

if (array_key_exists('mark-paid', $options)) {
    if (!$allowOverride) {
        fwrite(STDERR, "Refusing payment override: set PRINTFUL_DEV_ALLOW_PAYMENT_OVERRIDE=true only in the external development environment.\n");
        exit(1);
    }
    $reference = 'DEV-PAID-' . $orderId;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE orders SET payment_status = 'paid', payment_provider = 'development_override', payment_reference = :reference, payment_amount = total_amount, payment_currency = currency_code, paid_at = COALESCE(paid_at, NOW()), order_status = 'paid' WHERE id = :id AND payment_status IN ('unpaid', 'payment_pending', 'payment_failed')");
        $stmt->execute([':reference' => $reference, ':id' => $orderId]);
        $stmt = $pdo->prepare("INSERT INTO transactions (order_id, transaction_reference, payment_provider, transaction_type, currency_code, transaction_amount, product_sales_amount, shipping_amount, sales_tax_amount, transaction_status, processed_at, visible) SELECT id, :reference, 'development_override', 'sale', currency_code, total_amount, subtotal_amount, shipping_amount, tax_amount, 'paid', NOW(), 1 FROM orders WHERE id = :id AND payment_status = 'paid' AND NOT EXISTS (SELECT 1 FROM transactions WHERE order_id = :id2 AND transaction_reference = :reference2)");
        $stmt->execute([':reference' => $reference, ':id' => $orderId, ':id2' => $orderId, ':reference2' => $reference]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

try {
    $result = startOrderFulfillment($pdo, $orderId);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
