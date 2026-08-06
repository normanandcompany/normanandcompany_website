<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/env.php';

$pdo = normanCreateDatabaseConnection('admin');

$pdo->exec("CREATE TABLE IF NOT EXISTS download_category (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    description VARCHAR(180) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_download_category_description (description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$categories = [
    1 => 'Packing Lists',
    2 => 'Destination Guides',
    3 => 'Port Guides',
    4 => 'Kids Activity Library',
    5 => 'Vacation Planning Tools',
    6 => 'Cruise Planning Tools',
    7 => 'Travel Safety',
    8 => 'Travel Journals',
    9 => 'Group & Celebration Travel',
    10 => 'Before You Leave Home',
];

$seed = $pdo->prepare('INSERT INTO download_category (id, description)
    VALUES (:id, :description)
    ON DUPLICATE KEY UPDATE description = VALUES(description)');
foreach ($categories as $id => $description) {
    $seed->execute([':id' => $id, ':description' => $description]);
}

$columnExists = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'downloads'
      AND COLUMN_NAME = 'download_category_id'")->fetchColumn();
if ((int) $columnExists === 0) {
    // Nullable so downloads created before categories were introduced remain intact.
    $pdo->exec('ALTER TABLE downloads ADD COLUMN download_category_id INT UNSIGNED NULL AFTER id');
}

$indexExists = $pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'downloads'
      AND INDEX_NAME = 'idx_downloads_category'")->fetchColumn();
if ((int) $indexExists === 0) {
    $pdo->exec('ALTER TABLE downloads ADD KEY idx_downloads_category (download_category_id)');
}

$foreignKeyExists = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'downloads'
      AND CONSTRAINT_NAME = 'fk_downloads_category'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'")->fetchColumn();
if ((int) $foreignKeyExists === 0) {
    $pdo->exec('ALTER TABLE downloads ADD CONSTRAINT fk_downloads_category
        FOREIGN KEY (download_category_id) REFERENCES download_category(id)
        ON UPDATE CASCADE ON DELETE RESTRICT');
}

echo "Download categories migration complete.\n";
