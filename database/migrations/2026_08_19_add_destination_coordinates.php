<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/env.php';

$pdo = normanCreateDatabaseConnection('admin');

$columnExists = $pdo->prepare("SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'destinations'
      AND COLUMN_NAME = :column_name");

foreach (['latitude', 'longitude'] as $columnName) {
    $columnExists->execute([':column_name' => $columnName]);

    if ((int) $columnExists->fetchColumn() !== 0) {
        continue;
    }

    if ($columnName === 'latitude') {
        $pdo->exec('ALTER TABLE destinations ADD COLUMN latitude DECIMAL(9, 6) NULL AFTER updated_at');
        continue;
    }

    $pdo->exec('ALTER TABLE destinations ADD COLUMN longitude DECIMAL(9, 6) NULL AFTER latitude');
}

echo "Destination coordinate columns migration complete.\n";
