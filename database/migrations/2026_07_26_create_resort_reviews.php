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
    CREATE TABLE IF NOT EXISTS resort_reviews (
        id INT(11) NOT NULL AUTO_INCREMENT,
        resort_id INT(11) NOT NULL,
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
        UNIQUE KEY uq_resort_reviews_resort_user (resort_id, user_id),
        KEY idx_resort_reviews_public (resort_id, is_approved, created_at),
        KEY idx_resort_reviews_user (user_id),
        CONSTRAINT fk_resort_reviews_resort
            FOREIGN KEY (resort_id) REFERENCES resorts (id)
            ON UPDATE CASCADE ON DELETE CASCADE,
        CONSTRAINT fk_resort_reviews_user
            FOREIGN KEY (user_id) REFERENCES users (id)
            ON UPDATE CASCADE ON DELETE CASCADE,
        CONSTRAINT chk_resort_reviews_rating
            CHECK (rating BETWEEN 1 AND 5)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

echo "Resort reviews table migration complete.\n";
