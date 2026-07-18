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

function productSkuSnapshot(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, sku FROM products ORDER BY id');
    $snapshot = [];

    foreach ($stmt as $row) {
        $snapshot[(int) $row['id']] = $row['sku'];
    }

    return $snapshot;
}

$before = productSkuSnapshot($pdo);

$pdo->exec("
    CREATE TABLE IF NOT EXISTS product_sku_sequence (
        id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
        last_product_id INT NOT NULL
    ) ENGINE=InnoDB
");

$seed = (int) $pdo->query("
    SELECT GREATEST(
        COALESCE((SELECT MAX(id) FROM products), 0),
        COALESCE((
            SELECT AUTO_INCREMENT - 1
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'products'
        ), 0)
    )
")->fetchColumn();

$stmt = $pdo->prepare("
    INSERT INTO product_sku_sequence (id, last_product_id)
    VALUES (1, :seed)
    ON DUPLICATE KEY UPDATE
        last_product_id = GREATEST(last_product_id, VALUES(last_product_id))
");
$stmt->execute(['seed' => $seed]);

$pdo->exec('DROP TRIGGER IF EXISTS products_before_insert_set_sku');

$pdo->exec("
    CREATE TRIGGER products_before_insert_set_sku
    BEFORE INSERT ON products
    FOR EACH ROW
    BEGIN
        IF NEW.id IS NULL OR NEW.id = 0 THEN
            UPDATE product_sku_sequence
            SET last_product_id = LAST_INSERT_ID(last_product_id + 1)
            WHERE id = 1;

            SET NEW.id = LAST_INSERT_ID();
        ELSE
            UPDATE product_sku_sequence
            SET last_product_id = GREATEST(last_product_id, NEW.id)
            WHERE id = 1;
        END IF;

        SET NEW.sku = LPAD(
            CAST(NEW.id AS CHAR),
            GREATEST(4, CHAR_LENGTH(CAST(NEW.id AS CHAR))),
            '0'
        );
    END
");

$after = productSkuSnapshot($pdo);

if ($before !== $after) {
    throw new RuntimeException('Migration aborted: existing product SKU data changed unexpectedly.');
}

echo "Product SKU auto-generation migration complete.\n";
echo 'Existing product rows verified unchanged: ' . count($after) . "\n";
echo "Sequence seeded through product id: {$seed}\n";
