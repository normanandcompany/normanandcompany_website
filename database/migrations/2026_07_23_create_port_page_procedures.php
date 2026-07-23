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
    CREATE PROCEDURE IF NOT EXISTS sp_get_port_cards()
    SQL SECURITY DEFINER
    BEGIN
        SELECT
            id,
            port_name,
            city_name,
            country_name
        FROM ports
        WHERE COALESCE(visible, 1) = 1
        ORDER BY port_name, city_name, country_name;
    END
");

$pdo->exec("
    CREATE PROCEDURE IF NOT EXISTS sp_get_port_page(IN p_port_id INT)
    SQL SECURITY DEFINER
    BEGIN
        SELECT
            id,
            port_name,
            city_name,
            state_name,
            country_name,
            destination_id,
            description,
            latitude,
            longitude,
            image_url,
            visible,
            created_at,
            updated_at
        FROM ports
        WHERE id = p_port_id
          AND COALESCE(visible, 1) = 1
        LIMIT 1;
    END
");

echo "Port list and detail stored procedures migration complete.\n";
