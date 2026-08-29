<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/env.php';

$pdo = normanCreateDatabaseConnection('admin');

function variantPricingColumnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column');
    $stmt->execute([':table' => $table, ':column' => $column]);
    return (int) $stmt->fetchColumn() > 0;
}

$columns = [
    'product_variants' => [
        'price_adjustment' => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER size_label_snapshot',
        'printful_retail_price' => 'DECIMAL(10,2) NULL AFTER price_adjustment',
        'printful_currency' => 'CHAR(3) NULL AFTER printful_retail_price',
        'printful_price_synced_at' => 'DATETIME NULL AFTER printful_currency',
    ],
    'vendor_product_mappings' => [
        'pricing_mode' => 'VARCHAR(30) NULL AFTER mapping_status',
        'pricing_synced_at' => 'DATETIME NULL AFTER pricing_mode',
    ],
];

foreach ($columns as $table => $tableColumns) {
    foreach ($tableColumns as $column => $definition) {
        if (!variantPricingColumnExists($pdo, $table, $column)) {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    }
}

echo "Variant pricing schema is ready.\n";
