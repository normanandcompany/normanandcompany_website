<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/env.php';

$pdo = normanCreateDatabaseConnection('admin');

$statements = [
    "CREATE TABLE IF NOT EXISTS email_newsletters (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        newsletter_name VARCHAR(180) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        html_body MEDIUMTEXT NOT NULL,
        status ENUM('draft','queued','sending','completed','paused','cancelled') NOT NULL DEFAULT 'draft',
        created_by_user_id INT NOT NULL,
        queued_at DATETIME NULL,
        started_at DATETIME NULL,
        completed_at DATETIME NULL,
        last_batch_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_email_newsletters_status (status, last_batch_at),
        CONSTRAINT fk_email_newsletters_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id)
            ON UPDATE CASCADE ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS email_newsletter_recipients (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        newsletter_id INT UNSIGNED NOT NULL,
        user_id INT NULL,
        email_address VARCHAR(255) NOT NULL,
        first_name VARCHAR(100) NULL,
        last_name VARCHAR(100) NULL,
        status ENUM('pending','processing','sent','failed','skipped','unsubscribed') NOT NULL DEFAULT 'pending',
        attempt_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
        next_attempt_at DATETIME NULL,
        unsubscribe_token_hash CHAR(64) NOT NULL,
        sent_at DATETIME NULL,
        last_error VARCHAR(1000) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_newsletter_recipient (newsletter_id, email_address),
        KEY idx_newsletter_queue (newsletter_id, status, next_attempt_at),
        KEY idx_newsletter_unsubscribe (unsubscribe_token_hash),
        CONSTRAINT fk_newsletter_recipient_newsletter FOREIGN KEY (newsletter_id) REFERENCES email_newsletters(id)
            ON UPDATE CASCADE ON DELETE CASCADE,
        CONSTRAINT fk_newsletter_recipient_user FOREIGN KEY (user_id) REFERENCES users(id)
            ON UPDATE CASCADE ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS email_suppressions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        email_address VARCHAR(255) NOT NULL,
        reason ENUM('unsubscribe','manual','bounce','complaint') NOT NULL DEFAULT 'unsubscribe',
        source VARCHAR(50) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_email_suppressions_address (email_address)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS email_leads (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NULL,
        company VARCHAR(180) NULL,
        email_address VARCHAR(255) NOT NULL,
        status ENUM('active','unsubscribed','invalid','do_not_contact') NOT NULL DEFAULT 'active',
        last_campaign_id BIGINT UNSIGNED NULL,
        last_template_id INT UNSIGNED NULL,
        last_contacted_at DATETIME NULL,
        total_emails_sent INT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_email_leads_address (email_address),
        KEY idx_email_leads_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS email_signatures (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        signature_name VARCHAR(120) NOT NULL,
        html_body TEXT NOT NULL,
        is_default TINYINT(1) NOT NULL DEFAULT 0,
        created_by_user_id INT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        CONSTRAINT fk_email_signatures_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id)
            ON UPDATE CASCADE ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS email_sales_templates (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        template_name VARCHAR(180) NOT NULL,
        subject_template VARCHAR(255) NOT NULL,
        body_template TEXT NOT NULL,
        signature_id INT UNSIGNED NULL,
        created_by_user_id INT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_sales_templates_signature (signature_id),
        CONSTRAINT fk_sales_templates_signature FOREIGN KEY (signature_id) REFERENCES email_signatures(id)
            ON UPDATE CASCADE ON DELETE SET NULL,
        CONSTRAINT fk_sales_templates_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id)
            ON UPDATE CASCADE ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS email_sales_campaigns (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        campaign_name VARCHAR(180) NOT NULL,
        template_id INT UNSIGNED NOT NULL,
        status ENUM('draft','queued','sending','completed','paused','cancelled') NOT NULL DEFAULT 'draft',
        created_by_user_id INT NOT NULL,
        queued_at DATETIME NULL,
        started_at DATETIME NULL,
        completed_at DATETIME NULL,
        last_sent_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_sales_campaigns_status (status, last_sent_at),
        CONSTRAINT fk_sales_campaigns_template FOREIGN KEY (template_id) REFERENCES email_sales_templates(id)
            ON UPDATE CASCADE ON DELETE RESTRICT,
        CONSTRAINT fk_sales_campaigns_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id)
            ON UPDATE CASCADE ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS email_sales_recipients (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        campaign_id BIGINT UNSIGNED NOT NULL,
        lead_id BIGINT UNSIGNED NOT NULL,
        email_address VARCHAR(255) NOT NULL,
        first_name VARCHAR(100) NULL,
        last_name VARCHAR(100) NULL,
        company VARCHAR(180) NULL,
        subject_rendered VARCHAR(255) NULL,
        status ENUM('pending','processing','sent','failed','skipped','unsubscribed') NOT NULL DEFAULT 'pending',
        attempt_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
        next_attempt_at DATETIME NULL,
        unsubscribe_token_hash CHAR(64) NOT NULL,
        sent_at DATETIME NULL,
        last_error VARCHAR(1000) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_sales_campaign_lead (campaign_id, lead_id),
        KEY idx_sales_queue (campaign_id, status, next_attempt_at),
        KEY idx_sales_unsubscribe (unsubscribe_token_hash),
        CONSTRAINT fk_sales_recipient_campaign FOREIGN KEY (campaign_id) REFERENCES email_sales_campaigns(id)
            ON UPDATE CASCADE ON DELETE CASCADE,
        CONSTRAINT fk_sales_recipient_lead FOREIGN KEY (lead_id) REFERENCES email_leads(id)
            ON UPDATE CASCADE ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS email_delivery_log (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        message_type ENUM('newsletter','sales') NOT NULL,
        message_id BIGINT UNSIGNED NOT NULL,
        recipient_id BIGINT UNSIGNED NOT NULL,
        email_address VARCHAR(255) NOT NULL,
        status ENUM('sent','failed','skipped') NOT NULL,
        error_message VARCHAR(1000) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_email_delivery_message (message_type, message_id),
        KEY idx_email_delivery_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

foreach ($statements as $statement) {
    $pdo->exec($statement);
}

echo "Email Tools tables migration complete.\n";
