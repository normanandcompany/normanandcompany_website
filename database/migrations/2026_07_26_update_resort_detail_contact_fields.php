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
    ALTER TABLE resorts
        ADD COLUMN IF NOT EXISTS address VARCHAR(500) NULL AFTER city,
        ADD COLUMN IF NOT EXISTS phone_number VARCHAR(50) NULL AFTER address
");

$pdo->exec("
    CREATE OR REPLACE PROCEDURE sp_get_resort_page(IN p_resort_id INT)
    SQL SECURITY DEFINER
    BEGIN
        SELECT
            id,
            destination_id,
            resort_name,
            country,
            city,
            address,
            phone_number,
            resort_description,
            star_rating,
            website_url,
            image_url,
            seo_slug,
            latitude,
            longitude,
            visible,
            is_featured,
            created_at,
            updated_at
        FROM resorts
        WHERE id = p_resort_id
          AND COALESCE(visible, 1) = 1
        LIMIT 1;
    END
");

echo "Resort contact fields and detail stored procedure migration complete.\n";
