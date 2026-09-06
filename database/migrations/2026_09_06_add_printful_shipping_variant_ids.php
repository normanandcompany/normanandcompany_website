<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/env.php';

$pdo = normanCreateDatabaseConnection('admin');

function printfulShippingVariantColumnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column');
    $stmt->execute([':table' => $table, ':column' => $column]);
    return (int) $stmt->fetchColumn() > 0;
}

if (!printfulShippingVariantColumnExists($pdo, 'vendor_product_mappings', 'default_external_catalog_variant_id')) {
    $pdo->exec('ALTER TABLE vendor_product_mappings ADD COLUMN default_external_catalog_variant_id VARCHAR(100) NULL AFTER default_external_variant_id');
}

if (!printfulShippingVariantColumnExists($pdo, 'vendor_variant_mappings', 'external_catalog_variant_id')) {
    $pdo->exec('ALTER TABLE vendor_variant_mappings ADD COLUMN external_catalog_variant_id VARCHAR(100) NULL AFTER external_variant_id');
}

$pdo->exec("UPDATE vendor_variant_mappings
    SET external_catalog_variant_id = JSON_UNQUOTE(JSON_EXTRACT(external_data_json, '$.variant_id'))
    WHERE (external_catalog_variant_id IS NULL OR external_catalog_variant_id = '')
      AND external_data_json IS NOT NULL
      AND JSON_VALID(external_data_json)
      AND JSON_EXTRACT(external_data_json, '$.variant_id') IS NOT NULL");

echo "Printful shipping variant identifiers are ready.\n";
