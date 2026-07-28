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
    CREATE TABLE IF NOT EXISTS ship_reviews (
        id INT(11) NOT NULL AUTO_INCREMENT,
        ship_id INT(11) NOT NULL,
        user_id INT(11) NOT NULL,
        rating TINYINT UNSIGNED NOT NULL,
        review_title VARCHAR(150) NOT NULL,
        review_text TEXT NOT NULL,
        photo_data MEDIUMBLOB NULL DEFAULT NULL,
        photo_mime_type VARCHAR(50) NULL DEFAULT NULL,
        photo_original_name VARCHAR(255) NULL DEFAULT NULL,
        is_approved TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_ship_reviews_ship_user (ship_id, user_id),
        KEY idx_ship_reviews_public (ship_id, is_approved, created_at),
        KEY idx_ship_reviews_user (user_id),
        CONSTRAINT fk_ship_reviews_ship
            FOREIGN KEY (ship_id) REFERENCES ships (id)
            ON UPDATE CASCADE ON DELETE CASCADE,
        CONSTRAINT fk_ship_reviews_user
            FOREIGN KEY (user_id) REFERENCES users (id)
            ON UPDATE CASCADE ON DELETE CASCADE,
        CONSTRAINT chk_ship_reviews_rating
            CHECK (rating BETWEEN 1 AND 5)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

echo "Ship reviews table migration complete.\n";
