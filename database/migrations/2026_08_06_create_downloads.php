<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/env.php';

$pdo = normanCreateDatabaseConnection('admin');

$pdo->exec("CREATE TABLE IF NOT EXISTS downloads (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(180) NOT NULL,
    description TEXT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    download_key VARCHAR(64) NOT NULL,
    viewable TINYINT(1) NOT NULL DEFAULT 1,
    download_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_downloads_filename (filename),
    UNIQUE KEY uq_downloads_download_key (download_key),
    KEY idx_downloads_viewable (viewable, title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

echo "Downloads table migration complete.\n";
