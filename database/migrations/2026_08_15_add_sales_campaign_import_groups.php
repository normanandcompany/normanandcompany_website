<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/env.php';

$pdo = normanCreateDatabaseConnection('admin');

$pdo->exec("CREATE TABLE IF NOT EXISTS email_lead_imports (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    import_name VARCHAR(180) NOT NULL,
    source_filename VARCHAR(255) NOT NULL,
    imported_count INT UNSIGNED NOT NULL DEFAULT 0,
    invalid_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_by_user_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_email_lead_imports_created (created_at),
    CONSTRAINT fk_email_lead_imports_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS email_lead_import_members (
    import_id BIGINT UNSIGNED NOT NULL,
    lead_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (import_id, lead_id),
    KEY idx_email_lead_import_members_lead (lead_id),
    CONSTRAINT fk_email_lead_import_members_import FOREIGN KEY (import_id) REFERENCES email_lead_imports(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_email_lead_import_members_lead FOREIGN KEY (lead_id) REFERENCES email_leads(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$column = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_sales_campaigns' AND COLUMN_NAME='lead_import_id'");
if ((int) $column->fetchColumn() === 0) {
    $pdo->exec('ALTER TABLE email_sales_campaigns ADD COLUMN lead_import_id BIGINT UNSIGNED NULL AFTER template_id');
}

$index = $pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_sales_campaigns' AND INDEX_NAME='idx_sales_campaigns_import'");
if ((int) $index->fetchColumn() === 0) {
    $pdo->exec('ALTER TABLE email_sales_campaigns ADD KEY idx_sales_campaigns_import (lead_import_id)');
}

$constraint = $pdo->query("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='email_sales_campaigns' AND CONSTRAINT_NAME='fk_sales_campaigns_import'");
if ((int) $constraint->fetchColumn() === 0) {
    $pdo->exec('ALTER TABLE email_sales_campaigns ADD CONSTRAINT fk_sales_campaigns_import FOREIGN KEY (lead_import_id) REFERENCES email_lead_imports(id) ON UPDATE CASCADE ON DELETE RESTRICT');
}

echo "Sales campaign import groups migration complete.\n";
