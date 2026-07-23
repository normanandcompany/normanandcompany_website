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
    CREATE TABLE IF NOT EXISTS cruise_ducks (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        duck_action VARCHAR(20) NOT NULL,
        duck_id CHAR(6) NOT NULL,
        cruise_ship VARCHAR(25) NOT NULL,
        date_found DATE NOT NULL,
        general_location VARCHAR(100) NOT NULL,
        duck_message TEXT NULL DEFAULT NULL,
        photo_data MEDIUMBLOB NULL DEFAULT NULL,
        photo_mime_type VARCHAR(30) NULL DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_cruise_ducks_duck_id (duck_id),
        KEY idx_cruise_ducks_date_found (date_found),
        CONSTRAINT chk_cruise_ducks_action
            CHECK (duck_action IN ('keeping', 'rehiding', 'gifted'))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$constraintStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'cruise_ducks'
      AND CONSTRAINT_NAME = 'chk_cruise_ducks_action'
      AND CONSTRAINT_TYPE = 'CHECK'
");
$constraintStmt->execute();

if ((int) $constraintStmt->fetchColumn() === 0) {
    // Legacy entries used a blank action and were displayed as re-hidden ducks.
    $pdo->exec("
        UPDATE cruise_ducks
        SET duck_action = 'rehiding'
        WHERE duck_action = ''
    ");

    $pdo->exec("
        ALTER TABLE cruise_ducks
        ADD CONSTRAINT chk_cruise_ducks_action
            CHECK (duck_action IN ('keeping', 'rehiding', 'gifted'))
    ");
}

$pdo->exec("DROP PROCEDURE IF EXISTS sp_insert_cruise_duck");
$pdo->exec("
    CREATE PROCEDURE sp_insert_cruise_duck (
        IN p_duck_action VARCHAR(20),
        IN p_duck_id CHAR(6),
        IN p_cruise_ship VARCHAR(25),
        IN p_date_found DATE,
        IN p_general_location VARCHAR(100),
        IN p_duck_message TEXT,
        IN p_photo_data MEDIUMBLOB,
        IN p_photo_mime_type VARCHAR(30)
    )
    BEGIN
        INSERT INTO cruise_ducks (
            duck_action,
            duck_id,
            cruise_ship,
            date_found,
            general_location,
            duck_message,
            photo_data,
            photo_mime_type
        ) VALUES (
            p_duck_action,
            p_duck_id,
            p_cruise_ship,
            p_date_found,
            p_general_location,
            p_duck_message,
            p_photo_data,
            p_photo_mime_type
        );
    END
");

$pdo->exec("DROP PROCEDURE IF EXISTS sp_get_cruise_duck_history");
$pdo->exec("
    CREATE PROCEDURE sp_get_cruise_duck_history (IN p_duck_id CHAR(6))
    BEGIN
        SELECT
            id,
            duck_action,
            duck_id,
            cruise_ship,
            date_found,
            general_location,
            duck_message,
            photo_data,
            photo_mime_type,
            created_at
        FROM cruise_ducks
        WHERE duck_id = p_duck_id
        ORDER BY date_found ASC, created_at ASC, id ASC;
    END
");

echo "Cruise ducks table and stored procedure migration complete.\n";
