<?php

require_once 'transaction_helpers.php';
requireAdminTransactionJson();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendTransactionJson([
        'success' => false,
        'message' => 'Transactions can only be saved with POST.'
    ], 405);
}

try {
    $id = transactionIntRequired($_POST['id'] ?? '', 'Transaction ID');
    $orderId = transactionIntRequired($_POST['order_id'] ?? '', 'Order ID');
    $currencyCode = strtoupper(transactionRequiredString($_POST['currency_code'] ?? 'USD', 3, 'Currency'));

    if (!preg_match('/^[A-Z]{3}$/', $currencyCode)) {
        throw new InvalidArgumentException('Currency must be a three-letter code.');
    }

    if (!transactionRecordExists($pdo, 'transactions', $id)) {
        sendTransactionJson([
            'success' => false,
            'message' => 'Transaction not found.'
        ], 404);
    }

    if (!transactionRecordExists($pdo, 'orders', $orderId)) {
        throw new InvalidArgumentException('Selected order does not exist.');
    }

    $data = [
        'id' => $id,
        'order_id' => $orderId,
        'transaction_reference' => transactionStringOrNull($_POST['transaction_reference'] ?? '', 255, 'Reference'),
        'payment_provider' => transactionStringOrNull($_POST['payment_provider'] ?? '', 100, 'Payment provider'),
        'transaction_type' => transactionRequiredString($_POST['transaction_type'] ?? 'sale', 50, 'Transaction type'),
        'currency_code' => $currencyCode,
        'transaction_amount' => transactionDecimalOrNull($_POST['transaction_amount'] ?? '', 'Transaction total', true),
        'product_sales_amount' => transactionDecimalRequired($_POST['product_sales_amount'] ?? '', 'Product sales'),
        'product_cost_amount' => transactionDecimalRequired($_POST['product_cost_amount'] ?? '', 'Product cost'),
        'shipping_amount' => transactionDecimalRequired($_POST['shipping_amount'] ?? '', 'Shipping'),
        'return_amount' => transactionDecimalRequired($_POST['return_amount'] ?? '', 'Returns'),
        'sales_tax_amount' => transactionDecimalRequired($_POST['sales_tax_amount'] ?? '', 'Sales tax'),
        'discount_amount' => transactionDecimalRequired($_POST['discount_amount'] ?? '', 'Discounts'),
        'payment_fee_amount' => transactionDecimalRequired($_POST['payment_fee_amount'] ?? '', 'Payment fee'),
        'transaction_status' => transactionStringOrNull($_POST['transaction_status'] ?? '', 100, 'Transaction status'),
        'processed_at' => transactionDateTimeOrNull($_POST['processed_at'] ?? '', 'Processed at'),
        'visible' => isset($_POST['visible']) ? 1 : 0
    ];

    $sql = "
        UPDATE transactions
        SET
            order_id = :order_id,
            transaction_reference = :transaction_reference,
            payment_provider = :payment_provider,
            transaction_type = :transaction_type,
            currency_code = :currency_code,
            transaction_amount = :transaction_amount,
            product_sales_amount = :product_sales_amount,
            product_cost_amount = :product_cost_amount,
            shipping_amount = :shipping_amount,
            return_amount = :return_amount,
            sales_tax_amount = :sales_tax_amount,
            discount_amount = :discount_amount,
            payment_fee_amount = :payment_fee_amount,
            transaction_status = :transaction_status,
            processed_at = :processed_at,
            visible = :visible
        WHERE id = :id
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);

    sendTransactionJson([
        'success' => true,
        'id' => $id,
        'message' => 'Transaction updated.'
    ]);
} catch (Throwable $e) {
    sendTransactionJson([
        'success' => false,
        'message' => $e->getMessage()
    ], $e instanceof InvalidArgumentException ? 422 : 500);
}
