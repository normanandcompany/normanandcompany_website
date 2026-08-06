<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/env.php';

$pdo = normanCreateDatabaseConnection('admin');

$columns = [
    'newsletter_smtp_host' => 'VARCHAR(255) NULL',
    'newsletter_smtp_port' => 'SMALLINT UNSIGNED NULL',
    'newsletter_smtp_encryption' => "ENUM('tls','ssl','none') NULL",
    'newsletter_smtp_auth' => "ENUM('login','plain') NULL",
    'newsletter_smtp_username' => 'VARCHAR(255) NULL',
    'newsletter_smtp_password_encrypted' => 'TEXT NULL',
    'sales_smtp_host' => 'VARCHAR(255) NULL',
    'sales_smtp_port' => 'SMALLINT UNSIGNED NULL',
    'sales_smtp_encryption' => "ENUM('tls','ssl','none') NULL",
    'sales_smtp_auth' => "ENUM('login','plain') NULL",
    'sales_smtp_username' => 'VARCHAR(255) NULL',
    'sales_smtp_password_encrypted' => 'TEXT NULL'
];

foreach ($columns as $name => $definition) {
    $check = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_configuration' AND COLUMN_NAME=:column");
    $check->execute([':column' => $name]);
    if ((int) $check->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE email_configuration ADD COLUMN {$name} {$definition}");
    }
}

$pdo->exec("UPDATE email_configuration SET
    newsletter_smtp_host=COALESCE(newsletter_smtp_host,smtp_host),
    newsletter_smtp_port=COALESCE(newsletter_smtp_port,smtp_port),
    newsletter_smtp_encryption=COALESCE(newsletter_smtp_encryption,smtp_encryption),
    newsletter_smtp_auth=COALESCE(newsletter_smtp_auth,smtp_auth),
    newsletter_smtp_username=COALESCE(newsletter_smtp_username,smtp_username),
    newsletter_smtp_password_encrypted=COALESCE(newsletter_smtp_password_encrypted,smtp_password_encrypted),
    sales_smtp_host=COALESCE(sales_smtp_host,smtp_host),
    sales_smtp_port=COALESCE(sales_smtp_port,smtp_port),
    sales_smtp_encryption=COALESCE(sales_smtp_encryption,smtp_encryption),
    sales_smtp_auth=COALESCE(sales_smtp_auth,smtp_auth),
    sales_smtp_username=COALESCE(sales_smtp_username,smtp_username),
    sales_smtp_password_encrypted=COALESCE(sales_smtp_password_encrypted,smtp_password_encrypted)");

echo "Email server configuration split migration complete.\n";
