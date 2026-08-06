<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/env.php';

$pdo = normanCreateDatabaseConnection('admin');

$pdo->exec("CREATE TABLE IF NOT EXISTS email_newsletter_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_name VARCHAR(180) NOT NULL,
    subject_template VARCHAR(255) NOT NULL,
    html_body MEDIUMTEXT NOT NULL,
    created_by_user_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_newsletter_templates_name (template_name),
    CONSTRAINT fk_newsletter_templates_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS email_configuration (
    id TINYINT UNSIGNED NOT NULL,
    smtp_host VARCHAR(255) NOT NULL,
    smtp_port SMALLINT UNSIGNED NOT NULL DEFAULT 587,
    smtp_encryption ENUM('tls','ssl','none') NOT NULL DEFAULT 'tls',
    smtp_auth ENUM('login','plain') NOT NULL DEFAULT 'login',
    smtp_username VARCHAR(255) NOT NULL,
    smtp_password_encrypted TEXT NULL,
    newsletter_from_email VARCHAR(255) NOT NULL,
    newsletter_from_name VARCHAR(180) NOT NULL DEFAULT 'Norman and Company Newsletter',
    newsletter_reply_to VARCHAR(255) NOT NULL,
    sales_from_email VARCHAR(255) NOT NULL,
    sales_from_name VARCHAR(180) NOT NULL DEFAULT 'Norman and Company',
    sales_reply_to VARCHAR(255) NOT NULL,
    updated_by_user_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT chk_email_configuration_singleton CHECK (id = 1),
    CONSTRAINT fk_email_configuration_user FOREIGN KEY (updated_by_user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$column = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_newsletters' AND COLUMN_NAME='template_id'")->fetchColumn();
if ((int) $column === 0) {
    $pdo->exec('ALTER TABLE email_newsletters ADD COLUMN template_id INT UNSIGNED NULL AFTER id');
    $pdo->exec('ALTER TABLE email_newsletters ADD KEY idx_email_newsletters_template (template_id)');
    $pdo->exec('ALTER TABLE email_newsletters ADD CONSTRAINT fk_email_newsletters_template FOREIGN KEY (template_id) REFERENCES email_newsletter_templates(id) ON UPDATE CASCADE ON DELETE SET NULL');
}

// Preserve drafts made before templates and sends were separated.
$pdo->exec("INSERT INTO email_newsletter_templates(template_name,subject_template,html_body,created_by_user_id,created_at,updated_at)
    SELECT n.newsletter_name,n.subject,n.html_body,n.created_by_user_id,n.created_at,n.updated_at
    FROM email_newsletters n
    WHERE n.template_id IS NULL
      AND NOT EXISTS (SELECT 1 FROM email_newsletter_templates t
        WHERE t.template_name=n.newsletter_name AND t.subject_template=n.subject AND t.html_body=n.html_body)");
$pdo->exec("UPDATE email_newsletters n INNER JOIN email_newsletter_templates t
    ON t.template_name=n.newsletter_name AND t.subject_template=n.subject AND t.html_body=n.html_body
    SET n.template_id=t.id WHERE n.template_id IS NULL");

echo "Email Tools refinement migration complete.\n";
