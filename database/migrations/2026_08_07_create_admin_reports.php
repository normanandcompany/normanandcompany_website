<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/env.php';

$pdo = normanCreateDatabaseConnection('admin');

$pdo->exec("CREATE TABLE IF NOT EXISTS cloudflare_analytics_daily (
    metric_date DATE NOT NULL,
    requests BIGINT UNSIGNED NOT NULL DEFAULT 0,
    visits BIGINT UNSIGNED NOT NULL DEFAULT 0,
    bandwidth_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    cached_requests BIGINT UNSIGNED NULL,
    uncached_requests BIGINT UNSIGNED NULL,
    security_events BIGINT UNSIGNED NULL,
    captured_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (metric_date),
    KEY idx_cloudflare_analytics_captured (captured_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$procedures = [
    'sp_report_website_statistics' => <<<'REPORT_SQL'
CREATE PROCEDURE sp_report_website_statistics()
SQL SECURITY DEFINER
BEGIN
    SELECT 'Product views' AS metric,
           COALESCE(SUM(view_count), 0) AS metric_count,
           MIN(first_viewed_at) AS first_activity,
           MAX(last_viewed_at) AS last_activity
    FROM product_views
    UNION ALL
    SELECT 'Customer logins', COUNT(*), MIN(login_at), MAX(login_at)
    FROM user_login_logs
    UNION ALL
    SELECT 'Member news article views', COUNT(*), MIN(viewed_at), MAX(viewed_at)
    FROM user_news_article_views;
END
REPORT_SQL,
    'sp_report_cloudflare_analytics' => <<<'REPORT_SQL'
CREATE PROCEDURE sp_report_cloudflare_analytics()
SQL SECURITY DEFINER
BEGIN
    SELECT metric_date,
           requests,
           visits,
           bandwidth_bytes,
           cached_requests,
           uncached_requests,
           security_events,
           ROUND(CASE
               WHEN COALESCE(cached_requests, 0) + COALESCE(uncached_requests, 0) = 0 THEN 0
               ELSE cached_requests * 100.0 / (cached_requests + uncached_requests)
           END, 1) AS cache_hit_percentage,
           captured_at
    FROM cloudflare_analytics_daily
    ORDER BY metric_date DESC;
END
REPORT_SQL,
    'sp_report_users' => <<<'REPORT_SQL'
CREATE PROCEDURE sp_report_users()
SQL SECURITY DEFINER
BEGIN
    SELECT u.id AS user_id,
           u.first_name,
           u.last_name,
           u.email_address,
           u.phone,
           u.address_1,
           u.address_2,
           u.city,
           sp.name AS state_province,
           u.postal_code,
           u.country,
           ur.role_name,
           u.birthdate,
           u.sweepstakes_active,
           u.sweepstakes_won,
           u.sweepstakes_won_date,
           u.last_login_at,
           u.visible,
           u.is_active,
           u.created_at,
           u.updated_at
    FROM users u
    LEFT JOIN user_roles ur ON ur.id = u.user_role_id
    LEFT JOIN state_prov sp ON sp.id = u.state_prov_id
    ORDER BY u.created_at DESC, u.id DESC;
END
REPORT_SQL,
    'sp_report_products' => <<<'REPORT_SQL'
CREATE PROCEDURE sp_report_products()
SQL SECURITY DEFINER
BEGIN
    SELECT p.id AS product_id,
           p.sku,
           p.product_name,
           pc.category_name,
           v.vendor_name,
           bf.format_name,
           p.is_apparel,
           p.price,
           p.cost,
           p.inventory_count,
           COALESCE(pv.view_count, 0) AS lifetime_views,
           p.asin,
           p.isbn,
           p.is_featured,
           p.visible,
           p.is_active,
           p.created_at,
           p.updated_at
    FROM products p
    LEFT JOIN product_categories pc ON pc.id = p.product_category_id
    LEFT JOIN vendors v ON v.id = p.vendor_id
    LEFT JOIN book_formats bf ON bf.id = p.format_id
    LEFT JOIN product_views pv ON pv.product_id = p.id
    ORDER BY p.product_name, p.id;
END
REPORT_SQL,
    'sp_report_financial_summary' => <<<'REPORT_SQL'
CREATE PROCEDURE sp_report_financial_summary()
SQL SECURITY DEFINER
BEGIN
    SELECT DATE_FORMAT(COALESCE(t.processed_at, t.created_at), '%Y-%m') AS report_month,
           t.currency_code,
           COUNT(*) AS transaction_count,
           SUM(COALESCE(t.product_sales_amount, 0)) AS product_sales_amount,
           SUM(COALESCE(t.return_amount, 0)) AS return_amount,
           SUM(COALESCE(t.discount_amount, 0)) AS discount_amount,
           SUM(COALESCE(t.product_cost_amount, 0)) AS product_cost_amount,
           SUM(COALESCE(t.shipping_amount, 0)) AS shipping_amount,
           SUM(COALESCE(t.sales_tax_amount, 0)) AS sales_tax_amount,
           SUM(COALESCE(t.payment_fee_amount, 0)) AS payment_fee_amount,
           SUM(GREATEST(COALESCE(t.product_sales_amount, 0) - COALESCE(t.return_amount, 0) - COALESCE(t.discount_amount, 0), 0)) AS net_sales_amount,
           SUM(GREATEST(COALESCE(t.product_sales_amount, 0) - COALESCE(t.return_amount, 0) - COALESCE(t.discount_amount, 0) - COALESCE(t.product_cost_amount, 0), 0)) AS gross_profit_amount,
           SUM(GREATEST(COALESCE(t.transaction_amount, 0) - COALESCE(t.return_amount, 0) - COALESCE(t.payment_fee_amount, 0), 0)) AS net_proceeds_amount
    FROM transactions t
    WHERE COALESCE(t.visible, 1) = 1
      AND LOWER(COALESCE(t.transaction_status, '')) NOT IN ('failed','declined','void','voided','canceled','cancelled','pending','expired')
    GROUP BY DATE_FORMAT(COALESCE(t.processed_at, t.created_at), '%Y-%m'), t.currency_code
    ORDER BY report_month DESC, t.currency_code;
END
REPORT_SQL,
    'sp_report_downloads' => <<<'REPORT_SQL'
CREATE PROCEDURE sp_report_downloads()
SQL SECURITY DEFINER
BEGIN
    SELECT d.id AS download_id,
           d.title,
           dc.description AS category,
           d.description,
           d.filename,
           d.viewable,
           d.download_count,
           d.created_at,
           d.updated_at
    FROM downloads d
    LEFT JOIN download_category dc ON dc.id = d.download_category_id
    ORDER BY d.title, d.id;
END
REPORT_SQL,
    'sp_report_transactions' => <<<'REPORT_SQL'
CREATE PROCEDURE sp_report_transactions()
SQL SECURITY DEFINER
BEGIN
    SELECT t.id AS transaction_id,
           o.order_number,
           CONCAT(u.first_name, ' ', u.last_name) AS customer_name,
           u.email_address AS customer_email,
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
           GREATEST(COALESCE(t.product_sales_amount, 0) - COALESCE(t.return_amount, 0) - COALESCE(t.discount_amount, 0), 0) AS net_sales_amount,
           GREATEST(COALESCE(t.product_sales_amount, 0) - COALESCE(t.return_amount, 0) - COALESCE(t.discount_amount, 0) - COALESCE(t.product_cost_amount, 0), 0) AS gross_profit_amount,
           t.transaction_status,
           t.processed_at,
           t.visible,
           t.created_at
    FROM transactions t
    LEFT JOIN orders o ON o.id = t.order_id
    LEFT JOIN users u ON u.id = o.user_id
    ORDER BY COALESCE(t.processed_at, t.created_at) DESC, t.id DESC;
END
REPORT_SQL,
    'sp_report_email_activity' => <<<'REPORT_SQL'
CREATE PROCEDURE sp_report_email_activity()
SQL SECURITY DEFINER
BEGIN
    SELECT id AS delivery_id,
           message_type,
           message_id,
           recipient_id,
           email_address,
           status,
           error_message,
           created_at
    FROM email_delivery_log
    ORDER BY created_at DESC, id DESC;
END
REPORT_SQL,
    'sp_report_contacts' => <<<'REPORT_SQL'
CREATE PROCEDURE sp_report_contacts()
SQL SECURITY DEFINER
BEGIN
    SELECT id AS contact_id,
           full_name,
           email_address,
           phone_number,
           subject,
           message,
           created_at
    FROM norman_contacts
    ORDER BY created_at DESC, id DESC;
END
REPORT_SQL
];

foreach ($procedures as $name => $sql) {
    $pdo->exec('DROP PROCEDURE IF EXISTS ' . $name);
    $pdo->exec($sql);
}

echo "Admin report table and stored procedures migration complete.\n";
