<?php

require_once 'transaction_helpers.php';
requireAdminTransactionJson();
require_once 'db.php';

try {
    $status = transactionStringOrNull($_GET['transaction_status'] ?? $_GET['status'] ?? null, 100, 'Transaction status');
    $type = transactionStringOrNull($_GET['transaction_type'] ?? $_GET['type'] ?? null, 50, 'Transaction type');
    $params = [];
    $where = [];

    if ($status !== null && strtolower($status) !== 'all') {
        $where[] = 'LOWER(COALESCE(t.transaction_status, \'\')) = LOWER(:status)';
        $params[':status'] = $status;
    }

    if ($type !== null && strtolower($type) !== 'all') {
        $where[] = 'LOWER(COALESCE(t.transaction_type, \'\')) = LOWER(:type)';
        $params[':type'] = $type;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "
        SELECT
            t.id,
            t.order_id,
            t.sales_order_id,
            t.opportunity_id,
            t.lead_id,
            t.originating_campaign_id,
            COALESCE(o.order_number, so.order_number) AS order_number,
            COALESCE(o.order_status, so.status) AS order_status,
            COALESCE(o.total_amount, so.grand_total) AS order_total_amount,
            COALESCE(o.created_at, so.created_at) AS order_created_at,
            u.id AS user_id,
            COALESCE(CONCAT(u.first_name, ' ', u.last_name), so.contact_name_snapshot) AS customer_name,
            COALESCE(u.email_address, so.email_snapshot) AS customer_email,
            t.transaction_reference,
            t.payment_provider,
            t.transaction_type,
            t.currency_code,
            t.transaction_amount,
            t.product_sales_amount,
            t.product_cost_amount,
            t.shipping_amount,
            t.return_amount,
            t.sales_tax_amount,
            t.discount_amount,
            t.payment_fee_amount,
            t.transaction_status,
            t.processed_at,
            t.visible,
            t.created_at,
            CASE
                WHEN COALESCE(t.visible, 1) <> 1 THEN 0
                WHEN LOWER(COALESCE(t.transaction_status, '')) IN (
                    'failed',
                    'declined',
                    'void',
                    'voided',
                    'canceled',
                    'cancelled',
                    'pending',
                    'expired'
                ) THEN 0
                ELSE 1
            END AS is_posted,
            GREATEST(
                COALESCE(t.product_sales_amount, 0)
                - COALESCE(t.return_amount, 0)
                - COALESCE(t.discount_amount, 0),
                0
            ) AS net_sales_amount,
            GREATEST(
                COALESCE(t.product_sales_amount, 0)
                - COALESCE(t.return_amount, 0)
                - COALESCE(t.discount_amount, 0)
                - COALESCE(t.product_cost_amount, 0),
                0
            ) AS gross_profit_amount
        FROM transactions t
        LEFT JOIN orders o ON o.id = t.order_id
        LEFT JOIN sales_orders so ON so.id = t.sales_order_id
        LEFT JOIN users u ON u.id = o.user_id
        {$whereSql}
        ORDER BY COALESCE(t.processed_at, t.created_at) DESC, t.id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) {
    sendTransactionJson([
        'success' => false,
        'message' => $e->getMessage()
    ], $e instanceof InvalidArgumentException ? 422 : 500);
}
