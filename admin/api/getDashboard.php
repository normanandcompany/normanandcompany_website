<?php

header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/db.php';

function dashboardIntValue($value): int
{
    if ($value === null || $value === false || $value === '') {
        return 0;
    }

    return (int) $value;
}

function dashboardFloatValue($value): float
{
    if ($value === null || $value === false || $value === '') {
        return 0.0;
    }

    return (float) $value;
}

function dashboardMoneyValue($value): string
{
    return number_format(dashboardFloatValue($value), 2, '.', '');
}

function dashboardPercentValue($amount, $basis): string
{
    $basis = dashboardFloatValue($basis);

    if ($basis <= 0) {
        return '0.0';
    }

    return number_format((dashboardFloatValue($amount) / $basis) * 100, 1, '.', '');
}

function dashboardExecuteQuery(PDO $pdo, string $sql): PDOStatement
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    return $stmt;
}

function dashboardTableExists(PDO $pdo, string $table): bool
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        return false;
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table_name
    ");
    $stmt->execute(['table_name' => $table]);

    return dashboardIntValue($stmt->fetchColumn()) > 0;
}

function dashboardFetchOne(PDO $pdo, string $sql): int
{
    return dashboardIntValue(dashboardExecuteQuery($pdo, $sql)->fetchColumn());
}

function dashboardFetchRows(PDO $pdo, string $sql): array
{
    $integerFields = [
        'id',
        'product_id',
        'user_id',
        'view_count',
        'review_count',
        'sold_quantity',
        'inventory_count',
        'visible',
        'is_approved'
    ];
    $floatFields = [
        'average_rating',
        'gross_sales'
    ];

    $stmt = dashboardExecuteQuery($pdo, $sql);

    return array_map(static function (array $row) use ($integerFields, $floatFields): array {
        foreach ($integerFields as $field) {
            if (array_key_exists($field, $row)) {
                $row[$field] = dashboardIntValue($row[$field]);
            }
        }

        foreach ($floatFields as $field) {
            if (array_key_exists($field, $row)) {
                $row[$field] = $row[$field] === null ? 0 : (float) $row[$field];
            }
        }

        return $row;
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function dashboardFetchProductReviewMetrics(PDO $pdo): array
{
    $stmt = dashboardExecuteQuery($pdo, "
        SELECT
            COUNT(*) AS total_product_reviews,
            COALESCE(SUM(CASE WHEN is_approved = 1 THEN 1 ELSE 0 END), 0) AS approved_product_reviews,
            COALESCE(SUM(CASE WHEN is_approved = 0 THEN 1 ELSE 0 END), 0) AS pending_product_reviews,
            COALESCE(SUM(CASE WHEN visible = 1 AND is_approved = 1 THEN 1 ELSE 0 END), 0) AS public_product_reviews,
            COALESCE(SUM(CASE WHEN visible = 0 THEN 1 ELSE 0 END), 0) AS hidden_product_reviews,
            COALESCE(SUM(CASE WHEN rating >= 4 THEN 1 ELSE 0 END), 0) AS positive_product_reviews,
            COALESCE(SUM(CASE WHEN rating <= 2 THEN 1 ELSE 0 END), 0) AS low_rating_product_reviews,
            COALESCE(ROUND(AVG(rating), 1), 0) AS average_product_review_rating
        FROM product_reviews
    ");
    $metrics = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    foreach ([
        'total_product_reviews',
        'approved_product_reviews',
        'pending_product_reviews',
        'public_product_reviews',
        'hidden_product_reviews',
        'positive_product_reviews',
        'low_rating_product_reviews'
    ] as $field) {
        $metrics[$field] = dashboardIntValue($metrics[$field] ?? 0);
    }

    $metrics['average_product_review_rating'] = number_format((float) ($metrics['average_product_review_rating'] ?? 0), 1);

    return $metrics;
}

function dashboardFetchContactMetrics(PDO $pdo): array
{
    $stmt = dashboardExecuteQuery($pdo, "
        SELECT
            COUNT(*) AS total_contacts,
            COALESCE(SUM(CASE
                WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1
                ELSE 0
            END), 0) AS contacts_last_30_days
        FROM norman_contacts
    ");
    $metrics = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'total_contacts' => dashboardIntValue($metrics['total_contacts'] ?? 0),
        'contacts_last_30_days' => dashboardIntValue($metrics['contacts_last_30_days'] ?? 0)
    ];
}

function dashboardPostedOrderItemSalesSubquery(): string
{
    return "
        SELECT
            oi.product_id,
            SUM(COALESCE(oi.quantity, 0)) AS sold_quantity,
            SUM(
                GREATEST(
                    CASE
                        WHEN COALESCE(oi.line_subtotal, 0) > 0 THEN oi.line_subtotal
                        ELSE COALESCE(oi.quantity, 0) * COALESCE(oi.unit_price, 0)
                    END - COALESCE(oi.discount_amount, 0),
                    0
                )
            ) AS gross_sales
        FROM order_items oi
        INNER JOIN orders o ON o.id = oi.order_id
        WHERE oi.product_id IS NOT NULL
          AND COALESCE(o.visible, 1) = 1
          AND LOWER(COALESCE(o.order_status, '')) NOT IN (
              'cancelled',
              'canceled',
              'failed',
              'refunded',
              'returned',
              'void',
              'voided',
              'draft'
          )
          AND EXISTS (
              SELECT 1
              FROM transactions t
              WHERE t.order_id = oi.order_id
                AND COALESCE(t.visible, 1) = 1
                AND LOWER(COALESCE(t.transaction_type, 'sale')) NOT IN (
                    'refund',
                    'return',
                    'chargeback'
                )
                AND LOWER(COALESCE(t.transaction_status, '')) NOT IN (
                    'failed',
                    'declined',
                    'void',
                    'voided',
                    'canceled',
                    'cancelled',
                    'pending',
                    'expired',
                    'refund',
                    'refunded',
                    'returned',
                    'chargeback'
                )
          )
        GROUP BY oi.product_id
    ";
}

function dashboardFetchProductMovementCounts(PDO $pdo): array
{
    if (!dashboardTableExists($pdo, 'product_inventory_movements')) {
        return [
            'inbound_shipping_count' => 0,
            'outbound_shipping_count' => 0,
            'product_returns_count' => 0
        ];
    }

    $stmt = dashboardExecuteQuery($pdo, "
        SELECT
            COALESCE(SUM(CASE
                WHEN LOWER(movement_type) IN ('inbound_shipping', 'inbound') THEN quantity
                ELSE 0
            END), 0) AS inbound_shipping_count,
            COALESCE(SUM(CASE
                WHEN LOWER(movement_type) IN ('outbound_shipping', 'outbound') THEN quantity
                ELSE 0
            END), 0) AS outbound_shipping_count,
            COALESCE(SUM(CASE
                WHEN LOWER(movement_type) IN ('product_return', 'return', 'returned') THEN quantity
                ELSE 0
            END), 0) AS product_returns_count
        FROM product_inventory_movements
        WHERE COALESCE(visible, 1) = 1
          AND LOWER(COALESCE(movement_status, '')) NOT IN (
              'cancelled',
              'canceled',
              'void',
              'voided',
              'rejected',
              'failed'
          )
    ");

    $counts = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    foreach ([
        'inbound_shipping_count',
        'outbound_shipping_count',
        'product_returns_count'
    ] as $field) {
        $counts[$field] = dashboardIntValue($counts[$field] ?? 0);
    }

    return $counts;
}

function dashboardFetchProductInfoMetrics(PDO $pdo): array
{
    $metrics = dashboardFetchProductMovementCounts($pdo);
    $metrics['top_selling_products'] = [];
    $metrics['low_selling_products'] = dashboardFetchRows($pdo, "
        SELECT
            p.id,
            p.id AS product_id,
            p.product_name,
            0 AS sold_quantity,
            0 AS gross_sales
        FROM products p
        WHERE COALESCE(p.visible, 1) = 1
          AND COALESCE(p.is_active, 1) = 1
        ORDER BY p.product_name ASC
        LIMIT 5
    ");
    $metrics['low_inventory_products'] = dashboardFetchRows($pdo, "
        SELECT
            p.id,
            p.id AS product_id,
            p.product_name,
            p.sku,
            COALESCE(p.inventory_count, 0) AS inventory_count
        FROM products p
        WHERE COALESCE(p.visible, 1) = 1
          AND COALESCE(p.is_active, 1) = 1
        ORDER BY COALESCE(p.inventory_count, 0) ASC, p.product_name ASC
        LIMIT 5
    ");

    if (!dashboardTableExists($pdo, 'order_items')) {
        return $metrics;
    }

    $salesSubquery = dashboardPostedOrderItemSalesSubquery();

    $metrics['top_selling_products'] = dashboardFetchRows($pdo, "
        SELECT
            p.id,
            p.id AS product_id,
            p.product_name,
            COALESCE(s.sold_quantity, 0) AS sold_quantity,
            COALESCE(s.gross_sales, 0) AS gross_sales
        FROM products p
        INNER JOIN ({$salesSubquery}) s ON s.product_id = p.id
        WHERE COALESCE(p.visible, 1) = 1
          AND COALESCE(p.is_active, 1) = 1
          AND COALESCE(s.sold_quantity, 0) > 0
        ORDER BY s.sold_quantity DESC, s.gross_sales DESC, p.product_name ASC
        LIMIT 5
    ");
    $metrics['low_selling_products'] = dashboardFetchRows($pdo, "
        SELECT
            p.id,
            p.id AS product_id,
            p.product_name,
            COALESCE(s.sold_quantity, 0) AS sold_quantity,
            COALESCE(s.gross_sales, 0) AS gross_sales
        FROM products p
        LEFT JOIN ({$salesSubquery}) s ON s.product_id = p.id
        WHERE COALESCE(p.visible, 1) = 1
          AND COALESCE(p.is_active, 1) = 1
        ORDER BY COALESCE(s.sold_quantity, 0) ASC, COALESCE(s.gross_sales, 0) ASC, p.product_name ASC
        LIMIT 5
    ");

    return $metrics;
}

function dashboardFetchFinancialMetrics(PDO $pdo): array
{
    $stmt = dashboardExecuteQuery($pdo, "
        SELECT
            COALESCE(SUM(CASE WHEN is_posted = 1 THEN sales_amount ELSE 0 END), 0) AS total_sales,
            COALESCE(SUM(CASE WHEN is_posted = 1 AND metric_date >= MAKEDATE(YEAR(CURDATE()), 1) AND metric_date <= NOW() THEN sales_amount ELSE 0 END), 0) AS total_sales_ytd,
            COALESCE(SUM(CASE WHEN is_posted = 1 AND metric_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND metric_date <= NOW() THEN sales_amount ELSE 0 END), 0) AS total_sales_last_30,

            COALESCE(SUM(CASE WHEN is_posted = 1 THEN cost_amount ELSE 0 END), 0) AS total_cost,
            COALESCE(SUM(CASE WHEN is_posted = 1 AND metric_date >= MAKEDATE(YEAR(CURDATE()), 1) AND metric_date <= NOW() THEN cost_amount ELSE 0 END), 0) AS total_cost_ytd,
            COALESCE(SUM(CASE WHEN is_posted = 1 AND metric_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND metric_date <= NOW() THEN cost_amount ELSE 0 END), 0) AS total_cost_last_30,

            COALESCE(SUM(CASE WHEN is_posted = 1 THEN shipping_amount ELSE 0 END), 0) AS total_shipping,
            COALESCE(SUM(CASE WHEN is_posted = 1 AND metric_date >= MAKEDATE(YEAR(CURDATE()), 1) AND metric_date <= NOW() THEN shipping_amount ELSE 0 END), 0) AS total_shipping_ytd,
            COALESCE(SUM(CASE WHEN is_posted = 1 AND metric_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND metric_date <= NOW() THEN shipping_amount ELSE 0 END), 0) AS total_shipping_last_30,

            COALESCE(SUM(CASE WHEN is_posted = 1 THEN return_amount ELSE 0 END), 0) AS total_returns,
            COALESCE(SUM(CASE WHEN is_posted = 1 AND metric_date >= MAKEDATE(YEAR(CURDATE()), 1) AND metric_date <= NOW() THEN return_amount ELSE 0 END), 0) AS total_returns_ytd,
            COALESCE(SUM(CASE WHEN is_posted = 1 AND metric_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND metric_date <= NOW() THEN return_amount ELSE 0 END), 0) AS total_returns_last_30,

            COALESCE(SUM(CASE WHEN is_posted = 1 THEN sales_tax_amount ELSE 0 END), 0) AS total_sales_tax,
            COALESCE(SUM(CASE WHEN is_posted = 1 AND metric_date >= MAKEDATE(YEAR(CURDATE()), 1) AND metric_date <= NOW() THEN sales_tax_amount ELSE 0 END), 0) AS total_sales_tax_ytd,
            COALESCE(SUM(CASE WHEN is_posted = 1 AND metric_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND metric_date <= NOW() THEN sales_tax_amount ELSE 0 END), 0) AS total_sales_tax_last_30,

            COALESCE(SUM(CASE WHEN is_posted = 1 THEN sales_amount - return_amount - discount_amount ELSE 0 END), 0) AS net_sales,
            COALESCE(SUM(CASE WHEN is_posted = 1 AND metric_date >= MAKEDATE(YEAR(CURDATE()), 1) AND metric_date <= NOW() THEN sales_amount - return_amount - discount_amount ELSE 0 END), 0) AS net_sales_ytd,
            COALESCE(SUM(CASE WHEN is_posted = 1 AND metric_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND metric_date <= NOW() THEN sales_amount - return_amount - discount_amount ELSE 0 END), 0) AS net_sales_last_30,

            COALESCE(SUM(CASE WHEN is_posted = 1 THEN sales_amount - return_amount - discount_amount - cost_amount ELSE 0 END), 0) AS gross_profit,
            COALESCE(SUM(CASE WHEN is_posted = 1 AND metric_date >= MAKEDATE(YEAR(CURDATE()), 1) AND metric_date <= NOW() THEN sales_amount - return_amount - discount_amount - cost_amount ELSE 0 END), 0) AS gross_profit_ytd,
            COALESCE(SUM(CASE WHEN is_posted = 1 AND metric_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND metric_date <= NOW() THEN sales_amount - return_amount - discount_amount - cost_amount ELSE 0 END), 0) AS gross_profit_last_30,

            COALESCE(SUM(CASE WHEN is_posted = 1 THEN discount_amount ELSE 0 END), 0) AS total_discounts,
            COALESCE(SUM(CASE WHEN is_posted = 1 THEN payment_fee_amount ELSE 0 END), 0) AS total_payment_fees,

            COUNT(CASE WHEN is_posted = 1 THEN 1 END) AS posted_transactions,
            COUNT(CASE WHEN is_posted = 1 AND metric_date >= MAKEDATE(YEAR(CURDATE()), 1) AND metric_date <= NOW() THEN 1 END) AS posted_transactions_ytd,
            COUNT(CASE WHEN is_posted = 1 AND metric_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND metric_date <= NOW() THEN 1 END) AS posted_transactions_last_30
        FROM (
            SELECT
                COALESCE(t.processed_at, t.created_at) AS metric_date,
                CASE
                    WHEN COALESCE(t.visible, 1) <> 1 THEN 0
                    WHEN LOWER(COALESCE(t.transaction_status, '')) IN ('failed', 'declined', 'void', 'voided', 'canceled', 'cancelled', 'pending', 'expired') THEN 0
                    ELSE 1
                END AS is_posted,
                CASE
                    WHEN LOWER(COALESCE(t.transaction_type, 'sale')) IN ('refund', 'return', 'chargeback') THEN 0
                    WHEN LOWER(COALESCE(t.transaction_status, '')) IN ('refund', 'refunded', 'returned', 'chargeback') THEN 0
                    WHEN COALESCE(t.product_sales_amount, 0) > 0 THEN t.product_sales_amount
                    WHEN COALESCE(o.subtotal_amount, 0) > 0 THEN o.subtotal_amount
                    ELSE GREATEST(
                        COALESCE(t.transaction_amount, o.total_amount, 0)
                        - COALESCE(NULLIF(t.shipping_amount, 0), o.shipping_amount, 0)
                        - COALESCE(NULLIF(t.sales_tax_amount, 0), o.tax_amount, 0)
                        + COALESCE(t.discount_amount, 0),
                        0
                    )
                END AS sales_amount,
                COALESCE(t.product_cost_amount, 0) AS cost_amount,
                COALESCE(NULLIF(t.shipping_amount, 0), o.shipping_amount, 0) AS shipping_amount,
                CASE
                    WHEN COALESCE(t.return_amount, 0) > 0 THEN t.return_amount
                    WHEN LOWER(COALESCE(t.transaction_type, 'sale')) IN ('refund', 'return', 'chargeback') THEN ABS(COALESCE(t.transaction_amount, 0))
                    WHEN LOWER(COALESCE(t.transaction_status, '')) IN ('refund', 'refunded', 'returned', 'chargeback') THEN ABS(COALESCE(t.transaction_amount, 0))
                    ELSE 0
                END AS return_amount,
                COALESCE(NULLIF(t.sales_tax_amount, 0), o.tax_amount, 0) AS sales_tax_amount,
                COALESCE(t.discount_amount, 0) AS discount_amount,
                COALESCE(t.payment_fee_amount, 0) AS payment_fee_amount
            FROM transactions t
            LEFT JOIN orders o ON o.id = t.order_id
        ) transaction_metrics
    ");

    $metrics = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    foreach ([
        'total_sales',
        'total_sales_ytd',
        'total_sales_last_30',
        'total_cost',
        'total_cost_ytd',
        'total_cost_last_30',
        'total_shipping',
        'total_shipping_ytd',
        'total_shipping_last_30',
        'total_returns',
        'total_returns_ytd',
        'total_returns_last_30',
        'total_sales_tax',
        'total_sales_tax_ytd',
        'total_sales_tax_last_30',
        'net_sales',
        'net_sales_ytd',
        'net_sales_last_30',
        'gross_profit',
        'gross_profit_ytd',
        'gross_profit_last_30',
        'total_discounts',
        'total_payment_fees'
    ] as $field) {
        $metrics[$field] = dashboardMoneyValue($metrics[$field] ?? 0);
    }

    foreach ([
        'posted_transactions',
        'posted_transactions_ytd',
        'posted_transactions_last_30'
    ] as $field) {
        $metrics[$field] = dashboardIntValue($metrics[$field] ?? 0);
    }

    $metrics['product_margin'] = dashboardPercentValue($metrics['gross_profit'] ?? 0, $metrics['net_sales'] ?? 0);
    $metrics['product_margin_ytd'] = dashboardPercentValue($metrics['gross_profit_ytd'] ?? 0, $metrics['net_sales_ytd'] ?? 0);
    $metrics['product_margin_last_30'] = dashboardPercentValue($metrics['gross_profit_last_30'] ?? 0, $metrics['net_sales_last_30'] ?? 0);
    $metrics['average_sale'] = $metrics['posted_transactions'] > 0
        ? dashboardMoneyValue(dashboardFloatValue($metrics['total_sales']) / $metrics['posted_transactions'])
        : '0.00';
    $metrics['average_sale_ytd'] = $metrics['posted_transactions_ytd'] > 0
        ? dashboardMoneyValue(dashboardFloatValue($metrics['total_sales_ytd']) / $metrics['posted_transactions_ytd'])
        : '0.00';
    $metrics['average_sale_last_30'] = $metrics['posted_transactions_last_30'] > 0
        ? dashboardMoneyValue(dashboardFloatValue($metrics['total_sales_last_30']) / $metrics['posted_transactions_last_30'])
        : '0.00';

    return $metrics;
}

try {
    $stmt = $pdo->prepare("CALL sp_get_dashboard_info()");
    $stmt->execute();

    $dashboard = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmt->closeCursor();

    $dashboard = array_merge($dashboard, dashboardFetchProductInfoMetrics($pdo));

    $dashboard['total_product_views'] = dashboardFetchOne($pdo, "
        SELECT COALESCE(SUM(view_count), 0)
        FROM product_views
    ");
    $dashboard['viewed_products_count'] = dashboardFetchOne($pdo, "
        SELECT COUNT(*)
        FROM product_views
        WHERE view_count > 0
    ");
    $dashboard['products_without_views'] = dashboardFetchOne($pdo, "
        SELECT COUNT(*)
        FROM products p
        LEFT JOIN product_views pv ON pv.product_id = p.id
        WHERE p.visible = 1
          AND p.is_active = 1
          AND COALESCE(pv.view_count, 0) = 0
    ");
    $dashboard['average_views_per_viewed_product'] = $dashboard['viewed_products_count'] > 0
        ? round($dashboard['total_product_views'] / $dashboard['viewed_products_count'], 1)
        : 0;
    $dashboard['top_product_views'] = dashboardFetchRows($pdo, "
        SELECT
            p.id,
            p.product_name,
            pv.view_count
        FROM product_views pv
        INNER JOIN products p ON p.id = pv.product_id
        WHERE pv.view_count > 0
        ORDER BY pv.view_count DESC, pv.last_viewed_at DESC, p.product_name ASC
        LIMIT 5
    ");
    $dashboard['recent_product_views'] = dashboardFetchRows($pdo, "
        SELECT
            p.id,
            p.product_name,
            pv.view_count,
            pv.last_viewed_at
        FROM product_views pv
        INNER JOIN products p ON p.id = pv.product_id
        WHERE pv.last_viewed_at IS NOT NULL
        ORDER BY pv.last_viewed_at DESC, p.product_name ASC
        LIMIT 5
    ");
    $dashboard = array_merge($dashboard, dashboardFetchProductReviewMetrics($pdo));
    $dashboard['top_reviewed_products'] = dashboardFetchRows($pdo, "
        SELECT
            pr.product_id,
            COALESCE(p.product_name, CONCAT('Product #', pr.product_id)) AS product_name,
            COUNT(pr.id) AS review_count,
            COALESCE(ROUND(AVG(pr.rating), 1), 0) AS average_rating,
            MAX(pr.created_at) AS latest_reviewed_at
        FROM product_reviews pr
        LEFT JOIN products p ON p.id = pr.product_id
        GROUP BY pr.product_id, p.product_name
        ORDER BY review_count DESC, average_rating DESC, latest_reviewed_at DESC, product_name ASC
        LIMIT 5
    ");
    $dashboard['recent_product_reviews'] = dashboardFetchRows($pdo, "
        SELECT
            pr.id,
            pr.product_id,
            COALESCE(p.product_name, CONCAT('Product #', pr.product_id)) AS product_name,
            pr.user_id,
            NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), '') AS customer_name,
            u.email_address AS customer_email,
            pr.review_title,
            pr.review_content,
            pr.rating,
            pr.visible,
            pr.is_approved,
            pr.created_at
        FROM product_reviews pr
        LEFT JOIN products p ON p.id = pr.product_id
        LEFT JOIN users u ON u.id = pr.user_id
        ORDER BY
            CASE WHEN COALESCE(pr.is_approved, 0) = 0 THEN 0 ELSE 1 END,
            pr.created_at DESC,
            pr.id DESC
        LIMIT 8
    ");
    $dashboard = array_merge($dashboard, dashboardFetchContactMetrics($pdo));
    $dashboard['contacts'] = dashboardFetchRows($pdo, "
        SELECT
            id,
            full_name,
            email_address,
            phone_number,
            subject,
            message,
            created_at
        FROM norman_contacts
        ORDER BY created_at DESC, id DESC
    ");
    $dashboard = array_merge($dashboard, dashboardFetchFinancialMetrics($pdo));

    echo json_encode([$dashboard]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
