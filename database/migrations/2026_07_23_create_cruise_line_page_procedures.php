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

$pdo->exec('DROP PROCEDURE IF EXISTS sp_get_cruise_line_page');
$pdo->exec("
    CREATE PROCEDURE sp_get_cruise_line_page(IN p_page VARCHAR(30))
    SQL SECURITY DEFINER
    BEGIN
        SELECT
            cl.id,
            cl.cruise_line_name,
            cl.description,
            cl.headquarters_location,
            cl.website_url,
            cl.image_url,
            cl.image_size,
            cl.parent_company,
            cl.history,
            cl.cruising_area,
            cl.page,
            COUNT(s.id) AS ship_count
        FROM cruise_lines cl
        LEFT JOIN fleet_info fi
            ON fi.cruise_line = cl.id
        LEFT JOIN ships s
            ON s.id = fi.ships
            AND s.cruise_line_id = cl.id
            AND COALESCE(s.visible, 1) = 1
            AND COALESCE(s.is_active, 1) = 1
        WHERE cl.page = p_page
          AND COALESCE(cl.visible, 1) = 1
        GROUP BY
            cl.id,
            cl.cruise_line_name,
            cl.description,
            cl.headquarters_location,
            cl.website_url,
            cl.image_url,
            cl.image_size,
            cl.parent_company,
            cl.history,
            cl.cruising_area,
            cl.page
        LIMIT 1;

        SELECT
            s.id,
            s.ship_name,
            s.ship_class,
            s.passenger_capacity,
            s.gross_tonnage,
            s.launch_year,
            s.description,
            s.image_url
        FROM cruise_lines cl
        INNER JOIN fleet_info fi
            ON fi.cruise_line = cl.id
        INNER JOIN ships s
            ON s.id = fi.ships
            AND s.cruise_line_id = cl.id
        WHERE cl.page = p_page
          AND COALESCE(cl.visible, 1) = 1
          AND COALESCE(s.visible, 1) = 1
          AND COALESCE(s.is_active, 1) = 1
        ORDER BY s.ship_name;
    END
");

$pdo->exec('DROP PROCEDURE IF EXISTS sp_get_ship_page');
$pdo->exec("
    CREATE PROCEDURE sp_get_ship_page(IN p_ship_id INT)
    SQL SECURITY DEFINER
    BEGIN
        SELECT
            s.id,
            s.ship_name,
            s.ship_class,
            s.passenger_capacity,
            s.gross_tonnage,
            s.launch_year,
            s.description,
            s.image_url,
            cl.cruise_line_name,
            cl.page AS cruise_line_page
        FROM ships s
        INNER JOIN fleet_info fi
            ON fi.ships = s.id
            AND fi.cruise_line = s.cruise_line_id
        INNER JOIN cruise_lines cl
            ON cl.id = fi.cruise_line
        WHERE s.id = p_ship_id
          AND COALESCE(s.visible, 1) = 1
          AND COALESCE(s.is_active, 1) = 1
          AND COALESCE(cl.visible, 1) = 1
        LIMIT 1;
    END
");

echo "Cruise-line and ship page stored procedures migration complete.\n";
