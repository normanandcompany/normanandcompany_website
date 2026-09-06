<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/env.php';

$pdo = normanCreateDatabaseConnection('admin');

$pdo->beginTransaction();

try {
    $pdo->exec("UPDATE vendors
        SET fulfillment_provider = 'printful', visible = 1, is_active = 1
        WHERE LOWER(TRIM(vendor_name)) = 'printful'
          AND (fulfillment_provider IS NULL OR fulfillment_provider = 'printful')");

    $pdo->exec("INSERT INTO vendors (vendor_name, fulfillment_provider, visible, is_active)
        SELECT 'Printful', 'printful', 1, 1
        WHERE NOT EXISTS (
            SELECT 1 FROM vendors
            WHERE fulfillment_provider = 'printful' OR LOWER(TRIM(vendor_name)) = 'printful'
        )");

    $verify = $pdo->query("SELECT COUNT(*) FROM vendors
        WHERE fulfillment_provider = 'printful' OR LOWER(TRIM(vendor_name)) = 'printful'");
    if ((int) $verify->fetchColumn() !== 1) {
        throw new RuntimeException('Expected exactly one Printful vendor record.');
    }

    $pdo->commit();
    echo "Printful vendor record is ready.\n";
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $exception;
}
