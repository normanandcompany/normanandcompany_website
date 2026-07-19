<?php

$migrationUser = getenv('NORMAN_DB_MIGRATION_USER');

if ($migrationUser === false || $migrationUser === '') {
    require_once __DIR__ . '/../../admin/api/db.php';
} else {
    $migrationHost = getenv('NORMAN_DB_MIGRATION_HOST') ?: 'localhost';
    $migrationPort = getenv('NORMAN_DB_MIGRATION_PORT') ?: '8889';
    $migrationDb = getenv('NORMAN_DB_MIGRATION_DATABASE') ?: 'normanandcompany';
    $migrationPassword = getenv('NORMAN_DB_MIGRATION_PASSWORD');

    if ($migrationPassword === false) {
        fwrite(STDERR, 'Database password: ');
        $migrationPassword = rtrim((string) fgets(STDIN), "\r\n");
    }

    $pdo = new PDO(
        "mysql:host={$migrationHost};port={$migrationPort};dbname={$migrationDb};charset=utf8mb4",
        $migrationUser,
        $migrationPassword
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS order_items (
        id INT(11) NOT NULL AUTO_INCREMENT,
        order_id INT(11) NOT NULL,
        product_id INT(11) NULL DEFAULT NULL,
        product_sku VARCHAR(100) NULL DEFAULT NULL,
        product_name VARCHAR(255) NOT NULL,
        product_options VARCHAR(255) NULL DEFAULT NULL,
        quantity INT UNSIGNED NOT NULL DEFAULT 1,
        unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        unit_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        line_subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        shipping_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        currency_code CHAR(3) NOT NULL DEFAULT 'USD',
        fulfillment_status VARCHAR(50) NOT NULL DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_order_items_order (order_id),
        KEY idx_order_items_product (product_id),
        KEY idx_order_items_fulfillment (fulfillment_status),
        KEY idx_order_items_created_at (created_at),
        CONSTRAINT fk_order_items_order
            FOREIGN KEY (order_id)
            REFERENCES orders (id)
            ON UPDATE CASCADE
            ON DELETE CASCADE,
        CONSTRAINT fk_order_items_product
            FOREIGN KEY (product_id)
            REFERENCES products (id)
            ON UPDATE CASCADE
            ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS product_inventory_movements (
        id INT(11) NOT NULL AUTO_INCREMENT,
        product_id INT(11) NULL DEFAULT NULL,
        movement_type VARCHAR(50) NOT NULL,
        movement_status VARCHAR(50) NOT NULL DEFAULT 'pending',
        quantity INT UNSIGNED NOT NULL DEFAULT 0,
        reference_type VARCHAR(50) NULL DEFAULT NULL,
        reference_id INT(11) NULL DEFAULT NULL,
        notes TEXT NULL DEFAULT NULL,
        occurred_at DATETIME NULL DEFAULT NULL,
        visible TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_product_inventory_movements_product (product_id),
        KEY idx_product_inventory_movements_type_status (movement_type, movement_status),
        KEY idx_product_inventory_movements_occurred_at (occurred_at),
        CONSTRAINT fk_product_inventory_movements_product
            FOREIGN KEY (product_id)
            REFERENCES products (id)
            ON UPDATE CASCADE
            ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

echo "Product dashboard tables migration complete.\n";
