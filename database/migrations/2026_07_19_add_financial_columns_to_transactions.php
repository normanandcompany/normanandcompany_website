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

function transactionColumnExists(PDO $pdo, string $column): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'transactions'
          AND COLUMN_NAME = :column
    ");
    $stmt->execute(['column' => $column]);

    return (int) $stmt->fetchColumn() > 0;
}

function transactionIndexExists(PDO $pdo, string $index): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'transactions'
          AND INDEX_NAME = :index_name
    ");
    $stmt->execute(['index_name' => $index]);

    return (int) $stmt->fetchColumn() > 0;
}

$columns = [
    'transaction_type' => "VARCHAR(50) NOT NULL DEFAULT 'sale' AFTER payment_provider",
    'currency_code' => "CHAR(3) NOT NULL DEFAULT 'USD' AFTER transaction_type",
    'product_sales_amount' => "DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER transaction_amount",
    'product_cost_amount' => "DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER product_sales_amount",
    'shipping_amount' => "DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER product_cost_amount",
    'return_amount' => "DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER shipping_amount",
    'sales_tax_amount' => "DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER return_amount",
    'discount_amount' => "DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER sales_tax_amount",
    'payment_fee_amount' => "DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER discount_amount"
];

foreach ($columns as $column => $definition) {
    if (!transactionColumnExists($pdo, $column)) {
        $pdo->exec("ALTER TABLE transactions ADD COLUMN {$column} {$definition}");
    }
}

$indexes = [
    'idx_transactions_processed_at' => 'processed_at',
    'idx_transactions_status_visible' => 'transaction_status, visible',
    'idx_transactions_type' => 'transaction_type'
];

foreach ($indexes as $index => $definition) {
    if (!transactionIndexExists($pdo, $index)) {
        $pdo->exec("ALTER TABLE transactions ADD INDEX {$index} ({$definition})");
    }
}

echo "Transactions financial columns migration complete.\n";
