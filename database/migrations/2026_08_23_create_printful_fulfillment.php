<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/env.php';

$pdo = normanCreateDatabaseConnection('admin');

function printfulMigrationColumnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column');
    $stmt->execute([':table' => $table, ':column' => $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function printfulMigrationIndexExists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :index');
    $stmt->execute([':table' => $table, ':index' => $index]);
    return (int) $stmt->fetchColumn() > 0;
}

function printfulMigrationAddColumns(PDO $pdo, string $table, array $columns): void
{
    foreach ($columns as $name => $definition) {
        if (!printfulMigrationColumnExists($pdo, $table, $name)) {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$name}` {$definition}");
        }
    }
}

printfulMigrationAddColumns($pdo, 'vendors', [
    'fulfillment_provider' => 'VARCHAR(50) NULL AFTER vendor_name'
]);
if (!printfulMigrationIndexExists($pdo, 'vendors', 'uq_vendors_fulfillment_provider')) {
    $pdo->exec('ALTER TABLE vendors ADD UNIQUE KEY uq_vendors_fulfillment_provider (fulfillment_provider)');
}
$pdo->exec("UPDATE vendors SET fulfillment_provider = 'printful' WHERE LOWER(TRIM(vendor_name)) = 'printful' AND fulfillment_provider IS NULL");
$pdo->exec("INSERT INTO vendors (vendor_name, fulfillment_provider, visible, is_active)
    SELECT 'Printful', 'printful', 1, 1
    WHERE NOT EXISTS (
        SELECT 1 FROM vendors
        WHERE fulfillment_provider = 'printful' OR LOWER(TRIM(vendor_name)) = 'printful'
    )");

printfulMigrationAddColumns($pdo, 'apparel_sizes', [
    'is_active' => 'TINYINT(1) NOT NULL DEFAULT 1 AFTER name',
    'sort_order' => 'INT NOT NULL DEFAULT 0 AFTER is_active',
    'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER sort_order',
    'updated_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at'
]);

$pdo->exec("CREATE TABLE IF NOT EXISTS childrens_apparel_sizes (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_childrens_apparel_sizes_name (name),
    KEY idx_childrens_apparel_sizes_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$childSizes = ['Small', 'Medium', 'Large'];
$insertChildSize = $pdo->prepare('INSERT INTO childrens_apparel_sizes (name, sort_order) VALUES (:name, :sort_order) ON DUPLICATE KEY UPDATE name = VALUES(name)');
foreach ($childSizes as $index => $name) {
    $insertChildSize->execute([':name' => $name, ':sort_order' => ($index + 1) * 10]);
}

printfulMigrationAddColumns($pdo, 'products', [
    'apparel_size_type' => "VARCHAR(20) NULL COMMENT 'adult or children; null for non-apparel' AFTER is_apparel"
]);

$pdo->exec("CREATE TABLE IF NOT EXISTS product_variants (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT NOT NULL,
    adult_size_id INT NULL,
    childrens_size_id INT NULL,
    variant_sku VARCHAR(100) NULL,
    size_label_snapshot VARCHAR(50) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_product_variant_adult_size (product_id, adult_size_id),
    UNIQUE KEY uq_product_variant_child_size (product_id, childrens_size_id),
    KEY idx_product_variants_product_active (product_id, is_active, sort_order),
    CONSTRAINT fk_product_variants_product FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_product_variants_adult_size FOREIGN KEY (adult_size_id) REFERENCES apparel_sizes(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_product_variants_child_size FOREIGN KEY (childrens_size_id) REFERENCES childrens_apparel_sizes(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$triggerStmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = :name');
$triggerStmt->execute([':name' => 'trg_product_variants_one_size_insert']);
if (!(int) $triggerStmt->fetchColumn()) {
    $pdo->exec("CREATE TRIGGER trg_product_variants_one_size_insert BEFORE INSERT ON product_variants FOR EACH ROW BEGIN IF NOT ((NEW.adult_size_id IS NOT NULL AND NEW.childrens_size_id IS NULL) OR (NEW.adult_size_id IS NULL AND NEW.childrens_size_id IS NOT NULL)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A product variant must have exactly one adult or children size'; END IF; END");
}
$triggerStmt->execute([':name' => 'trg_product_variants_one_size_update']);
if (!(int) $triggerStmt->fetchColumn()) {
    $pdo->exec("CREATE TRIGGER trg_product_variants_one_size_update BEFORE UPDATE ON product_variants FOR EACH ROW BEGIN IF NOT ((NEW.adult_size_id IS NOT NULL AND NEW.childrens_size_id IS NULL) OR (NEW.adult_size_id IS NULL AND NEW.childrens_size_id IS NOT NULL)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A product variant must have exactly one adult or children size'; END IF; END");
}

$pdo->exec("CREATE TABLE IF NOT EXISTS vendor_product_mappings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT NOT NULL,
    vendor_id INT NOT NULL,
    external_product_id VARCHAR(100) NOT NULL,
    external_product_name VARCHAR(255) NULL,
    default_external_variant_id VARCHAR(100) NULL,
    default_external_variant_name VARCHAR(255) NULL,
    default_external_sku VARCHAR(100) NULL,
    default_availability_status VARCHAR(50) NULL,
    external_data_json LONGTEXT NULL,
    mapping_status VARCHAR(40) NOT NULL DEFAULT 'active',
    last_synced_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vendor_product_mapping (product_id, vendor_id),
    KEY idx_vendor_product_external (vendor_id, external_product_id),
    CONSTRAINT fk_vendor_product_mapping_product FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_vendor_product_mapping_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

printfulMigrationAddColumns($pdo, 'vendor_product_mappings', [
    'default_external_variant_id' => 'VARCHAR(100) NULL AFTER external_product_name',
    'default_external_variant_name' => 'VARCHAR(255) NULL AFTER default_external_variant_id',
    'default_external_sku' => 'VARCHAR(100) NULL AFTER default_external_variant_name',
    'default_availability_status' => 'VARCHAR(50) NULL AFTER default_external_sku'
]);

$pdo->exec("CREATE TABLE IF NOT EXISTS vendor_variant_mappings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_variant_id BIGINT UNSIGNED NOT NULL,
    vendor_product_mapping_id BIGINT UNSIGNED NOT NULL,
    external_variant_id VARCHAR(100) NOT NULL,
    external_variant_name VARCHAR(255) NULL,
    external_sku VARCHAR(100) NULL,
    external_color VARCHAR(100) NULL,
    external_size VARCHAR(100) NULL,
    availability_status VARCHAR(50) NULL,
    external_data_json LONGTEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_synced_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vendor_variant_mapping (product_variant_id, vendor_product_mapping_id),
    KEY idx_vendor_variant_external (vendor_product_mapping_id, external_variant_id),
    CONSTRAINT fk_vendor_variant_mapping_variant FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_vendor_variant_mapping_product FOREIGN KEY (vendor_product_mapping_id) REFERENCES vendor_product_mappings(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

printfulMigrationAddColumns($pdo, 'orders', [
    'payment_status' => "VARCHAR(40) NOT NULL DEFAULT 'unpaid' AFTER order_status",
    'payment_provider' => 'VARCHAR(100) NULL AFTER payment_status',
    'payment_reference' => 'VARCHAR(255) NULL AFTER payment_provider',
    'payment_amount' => 'DECIMAL(10,2) NULL AFTER payment_reference',
    'payment_currency' => "CHAR(3) NOT NULL DEFAULT 'USD' AFTER payment_amount",
    'paid_at' => 'DATETIME NULL AFTER payment_currency',
    'fulfillment_status' => "VARCHAR(50) NOT NULL DEFAULT 'pending' AFTER paid_at",
    'shipping_name' => 'VARCHAR(220) NULL AFTER fulfillment_status',
    'shipping_email' => 'VARCHAR(255) NULL AFTER shipping_name',
    'shipping_phone' => 'VARCHAR(50) NULL AFTER shipping_email',
    'shipping_address1' => 'VARCHAR(255) NULL AFTER shipping_phone',
    'shipping_address2' => 'VARCHAR(255) NULL AFTER shipping_address1',
    'shipping_city' => 'VARCHAR(100) NULL AFTER shipping_address2',
    'shipping_state_code' => 'VARCHAR(10) NULL AFTER shipping_city',
    'shipping_country_code' => 'CHAR(2) NULL AFTER shipping_state_code',
    'shipping_postal_code' => 'VARCHAR(25) NULL AFTER shipping_country_code',
    'currency_code' => "CHAR(3) NOT NULL DEFAULT 'USD' AFTER shipping_postal_code"
]);
foreach ([
    'idx_orders_payment_status' => '(payment_status, created_at)',
    'idx_orders_fulfillment_status' => '(fulfillment_status, created_at)',
    'uq_orders_payment_reference' => '(payment_provider, payment_reference)'
] as $index => $columns) {
    if (!printfulMigrationIndexExists($pdo, 'orders', $index)) {
        $type = str_starts_with($index, 'uq_') ? 'UNIQUE KEY' : 'KEY';
        $pdo->exec("ALTER TABLE orders ADD {$type} {$index} {$columns}");
    }
}

printfulMigrationAddColumns($pdo, 'order_items', [
    'product_variant_id' => 'BIGINT UNSIGNED NULL AFTER product_id',
    'vendor_id' => 'INT NULL AFTER product_variant_id',
    'size_label' => 'VARCHAR(50) NULL AFTER product_options'
]);
if (!printfulMigrationIndexExists($pdo, 'order_items', 'idx_order_items_variant')) {
    $pdo->exec('ALTER TABLE order_items ADD KEY idx_order_items_variant (product_variant_id), ADD KEY idx_order_items_vendor (vendor_id), ADD CONSTRAINT fk_order_items_variant FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON UPDATE CASCADE ON DELETE SET NULL, ADD CONSTRAINT fk_order_items_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON UPDATE CASCADE ON DELETE SET NULL');
}

$pdo->exec("CREATE TABLE IF NOT EXISTS checkout_shipping_quotes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    quote_token CHAR(64) NOT NULL,
    user_id INT NULL,
    vendor_id INT NOT NULL,
    cart_hash CHAR(64) NOT NULL,
    address_hash CHAR(64) NOT NULL,
    country_code CHAR(2) NOT NULL,
    currency_code CHAR(3) NOT NULL DEFAULT 'USD',
    rates_json LONGTEXT NOT NULL,
    selected_rate_id VARCHAR(100) NULL,
    selected_rate_name VARCHAR(255) NULL,
    selected_rate_amount DECIMAL(10,2) NULL,
    consumed_order_id INT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_checkout_shipping_quote_token (quote_token),
    UNIQUE KEY uq_checkout_shipping_quote_order (consumed_order_id),
    KEY idx_checkout_shipping_quote_user (user_id, expires_at),
    CONSTRAINT fk_checkout_shipping_quote_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_checkout_shipping_quote_order FOREIGN KEY (consumed_order_id) REFERENCES orders(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_checkout_shipping_quote_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS vendor_fulfillments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id INT NOT NULL,
    vendor_id INT NOT NULL,
    external_order_id VARCHAR(100) NULL,
    external_reference VARCHAR(100) NOT NULL,
    fulfillment_status VARCHAR(50) NOT NULL DEFAULT 'pending',
    vendor_order_status VARCHAR(50) NULL,
    shipping_service_id VARCHAR(100) NULL,
    shipping_service_name VARCHAR(255) NULL,
    shipping_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    currency_code CHAR(3) NOT NULL DEFAULT 'USD',
    submitted_at DATETIME NULL,
    confirmed_at DATETIME NULL,
    last_synced_at DATETIME NULL,
    last_error_code VARCHAR(80) NULL,
    last_error_message TEXT NULL,
    retry_count INT UNSIGNED NOT NULL DEFAULT 0,
    next_retry_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vendor_fulfillment_order_vendor (order_id, vendor_id),
    UNIQUE KEY uq_vendor_fulfillment_external_reference (vendor_id, external_reference),
    UNIQUE KEY uq_vendor_fulfillment_external_order (vendor_id, external_order_id),
    KEY idx_vendor_fulfillment_status_retry (fulfillment_status, next_retry_at),
    CONSTRAINT fk_vendor_fulfillment_order FOREIGN KEY (order_id) REFERENCES orders(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_vendor_fulfillment_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS vendor_fulfillment_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    vendor_fulfillment_id BIGINT UNSIGNED NOT NULL,
    order_item_id INT NOT NULL,
    vendor_variant_mapping_id BIGINT UNSIGNED NULL,
    external_line_item_id VARCHAR(100) NULL,
    quantity INT UNSIGNED NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vendor_fulfillment_order_item (vendor_fulfillment_id, order_item_id),
    CONSTRAINT fk_vendor_fulfillment_item_parent FOREIGN KEY (vendor_fulfillment_id) REFERENCES vendor_fulfillments(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_vendor_fulfillment_item_order_item FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_vendor_fulfillment_item_mapping FOREIGN KEY (vendor_variant_mapping_id) REFERENCES vendor_variant_mappings(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS fulfillment_shipments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    vendor_fulfillment_id BIGINT UNSIGNED NOT NULL,
    external_shipment_id VARCHAR(100) NOT NULL,
    shipment_status VARCHAR(50) NOT NULL DEFAULT 'pending',
    carrier VARCHAR(100) NULL,
    service VARCHAR(100) NULL,
    tracking_number VARCHAR(255) NULL,
    tracking_url VARCHAR(1000) NULL,
    shipped_at DATETIME NULL,
    estimated_delivery_at DATETIME NULL,
    delivered_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fulfillment_shipment_external (vendor_fulfillment_id, external_shipment_id),
    KEY idx_fulfillment_shipments_tracking (tracking_number),
    CONSTRAINT fk_fulfillment_shipment_parent FOREIGN KEY (vendor_fulfillment_id) REFERENCES vendor_fulfillments(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS fulfillment_shipment_items (
    shipment_id BIGINT UNSIGNED NOT NULL,
    vendor_fulfillment_item_id BIGINT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (shipment_id, vendor_fulfillment_item_id),
    CONSTRAINT fk_fulfillment_shipment_items_shipment FOREIGN KEY (shipment_id) REFERENCES fulfillment_shipments(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_fulfillment_shipment_items_item FOREIGN KEY (vendor_fulfillment_item_id) REFERENCES vendor_fulfillment_items(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS fulfillment_webhook_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider VARCHAR(50) NOT NULL,
    event_hash CHAR(64) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    external_order_id VARCHAR(100) NULL,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    processing_status VARCHAR(30) NOT NULL DEFAULT 'received',
    failure_message TEXT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fulfillment_webhook_event (provider, event_hash),
    KEY idx_fulfillment_webhook_processing (processing_status, received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS fulfillment_api_health (
    provider VARCHAR(50) NOT NULL,
    last_success_at DATETIME NULL,
    last_failure_at DATETIME NULL,
    last_status_code INT NULL,
    last_error_category VARCHAR(80) NULL,
    last_error_message VARCHAR(500) NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (provider)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

echo "Printful fulfillment migration complete.\n";
