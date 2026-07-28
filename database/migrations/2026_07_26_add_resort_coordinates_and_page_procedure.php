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
        ADD COLUMN IF NOT EXISTS latitude DECIMAL(10, 7) NULL AFTER seo_slug,
        ADD COLUMN IF NOT EXISTS longitude DECIMAL(10, 7) NULL AFTER latitude
");

$pdo->exec("
    CREATE PROCEDURE IF NOT EXISTS sp_get_resort_cards()
    SQL SECURITY DEFINER
    BEGIN
        SELECT
            id,
            resort_name,
            city,
            country
        FROM resorts
        WHERE COALESCE(visible, 1) = 1
        ORDER BY resort_name, city, country;
    END
");

$pdo->exec("
    CREATE PROCEDURE IF NOT EXISTS sp_get_resort_page(IN p_resort_id INT)
    SQL SECURITY DEFINER
    BEGIN
        SELECT
            id,
            destination_id,
            resort_name,
            country,
            city,
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

echo "Resort coordinate columns and page stored procedures migration complete.\n";
