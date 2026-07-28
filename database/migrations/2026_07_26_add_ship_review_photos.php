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
    ALTER TABLE ship_reviews
        ADD COLUMN IF NOT EXISTS photo_data MEDIUMBLOB NULL DEFAULT NULL AFTER review_text,
        ADD COLUMN IF NOT EXISTS photo_mime_type VARCHAR(50) NULL DEFAULT NULL AFTER photo_data,
        ADD COLUMN IF NOT EXISTS photo_original_name VARCHAR(255) NULL DEFAULT NULL AFTER photo_mime_type
");

echo "Ship review photo columns migration complete.\n";
