<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/env.php';

$pdo = normanCreateDatabaseConnection('admin');

function crmColumnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table AND COLUMN_NAME=:column');
    $stmt->execute([':table' => $table, ':column' => $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function crmIndexExists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table AND INDEX_NAME=:index');
    $stmt->execute([':table' => $table, ':index' => $index]);
    return (int) $stmt->fetchColumn() > 0;
}

function crmConstraintExists(PDO $pdo, string $table, string $constraint): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=:table AND CONSTRAINT_NAME=:constraint');
    $stmt->execute([':table' => $table, ':constraint' => $constraint]);
    return (int) $stmt->fetchColumn() > 0;
}

$leadColumns = [
    'lead_status' => "VARCHAR(40) NOT NULL DEFAULT 'New' AFTER status",
    'lead_source' => "VARCHAR(80) NOT NULL DEFAULT 'Other' AFTER company",
    'phone' => 'VARCHAR(50) NULL AFTER email_address',
    'job_title' => 'VARCHAR(140) NULL AFTER phone',
    'assigned_user_id' => 'INT NULL AFTER job_title',
    'priority' => "VARCHAR(20) NOT NULL DEFAULT 'Normal' AFTER assigned_user_id",
    'qualification_score' => 'TINYINT UNSIGNED NULL AFTER priority',
    'qualification_notes' => 'TEXT NULL AFTER qualification_score',
    'qualified_at' => 'DATETIME NULL AFTER qualification_notes',
    'qualified_by_user_id' => 'INT NULL AFTER qualified_at',
    'disqualification_reason' => 'VARCHAR(255) NULL AFTER qualified_by_user_id',
    'next_follow_up_at' => 'DATETIME NULL AFTER last_contacted_at',
    'notes' => 'TEXT NULL AFTER next_follow_up_at',
    'archived_at' => 'DATETIME NULL AFTER updated_at'
];
foreach ($leadColumns as $name => $definition) {
    if (!crmColumnExists($pdo, 'email_leads', $name)) {
        $pdo->exec("ALTER TABLE email_leads ADD COLUMN `{$name}` {$definition}");
    }
}
foreach ([
    'idx_email_leads_lifecycle' => '(lead_status, archived_at)',
    'idx_email_leads_owner' => '(assigned_user_id)',
    'idx_email_leads_source' => '(lead_source)',
    'idx_email_leads_follow_up' => '(next_follow_up_at)'
] as $name => $columns) {
    if (!crmIndexExists($pdo, 'email_leads', $name)) {
        $pdo->exec("ALTER TABLE email_leads ADD KEY {$name} {$columns}");
    }
}
foreach ([
    'fk_email_leads_owner' => ['assigned_user_id', 'users', 'id'],
    'fk_email_leads_qualifier' => ['qualified_by_user_id', 'users', 'id']
] as $name => [$column, $parent, $parentColumn]) {
    if (!crmConstraintExists($pdo, 'email_leads', $name)) {
        $pdo->exec("ALTER TABLE email_leads ADD CONSTRAINT {$name} FOREIGN KEY ({$column}) REFERENCES {$parent}({$parentColumn}) ON UPDATE CASCADE ON DELETE SET NULL");
    }
}

$pdo->exec("CREATE TABLE IF NOT EXISTS opportunities (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    lead_id BIGINT UNSIGNED NOT NULL,
    opportunity_name VARCHAR(180) NOT NULL,
    assigned_user_id INT NULL,
    stage VARCHAR(40) NOT NULL DEFAULT 'New',
    probability TINYINT UNSIGNED NOT NULL DEFAULT 10,
    estimated_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    expected_close_date DATE NULL,
    source VARCHAR(80) NULL,
    originating_campaign_id BIGINT UNSIGNED NULL,
    description TEXT NULL,
    notes TEXT NULL,
    lost_reason VARCHAR(255) NULL,
    won_at DATETIME NULL,
    lost_at DATETIME NULL,
    archived_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_opportunities_lead (lead_id),
    KEY idx_opportunities_stage_close (stage, expected_close_date),
    KEY idx_opportunities_owner (assigned_user_id),
    KEY idx_opportunities_campaign (originating_campaign_id),
    CONSTRAINT fk_opportunities_lead FOREIGN KEY (lead_id) REFERENCES email_leads(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_opportunities_owner FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_opportunities_campaign FOREIGN KEY (originating_campaign_id) REFERENCES email_sales_campaigns(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS opportunity_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    opportunity_id BIGINT UNSIGNED NOT NULL,
    product_id INT NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    proposed_unit_price DECIMAL(12,2) NOT NULL,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(12,2) NOT NULL,
    notes VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_opportunity_product (opportunity_id, product_id),
    KEY idx_opportunity_items_product (product_id),
    CONSTRAINT fk_opportunity_items_opportunity FOREIGN KEY (opportunity_id) REFERENCES opportunities(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_opportunity_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS sales_orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_number VARCHAR(30) NULL,
    lead_id BIGINT UNSIGNED NOT NULL,
    opportunity_id BIGINT UNSIGNED NULL,
    assigned_user_id INT NULL,
    originating_campaign_id BIGINT UNSIGNED NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'Draft',
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    shipping_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    grand_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    company_snapshot VARCHAR(180) NULL,
    contact_name_snapshot VARCHAR(220) NOT NULL,
    email_snapshot VARCHAR(255) NOT NULL,
    phone_snapshot VARCHAR(50) NULL,
    billing_address_snapshot TEXT NULL,
    shipping_address_snapshot TEXT NULL,
    customer_notes TEXT NULL,
    internal_notes TEXT NULL,
    sent_at DATETIME NULL,
    accepted_at DATETIME NULL,
    paid_at DATETIME NULL,
    fulfilled_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sales_orders_number (order_number),
    KEY idx_sales_orders_lead (lead_id),
    KEY idx_sales_orders_opportunity (opportunity_id),
    KEY idx_sales_orders_status_created (status, created_at),
    KEY idx_sales_orders_owner (assigned_user_id),
    KEY idx_sales_orders_campaign (originating_campaign_id),
    CONSTRAINT fk_sales_orders_lead FOREIGN KEY (lead_id) REFERENCES email_leads(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_sales_orders_opportunity FOREIGN KEY (opportunity_id) REFERENCES opportunities(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_sales_orders_owner FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_sales_orders_campaign FOREIGN KEY (originating_campaign_id) REFERENCES email_sales_campaigns(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS sales_order_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sales_order_id BIGINT UNSIGNED NOT NULL,
    product_id INT NULL,
    product_name_snapshot VARCHAR(255) NOT NULL,
    sku_snapshot VARCHAR(100) NULL,
    isbn_snapshot VARCHAR(20) NULL,
    product_description_snapshot TEXT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(12,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sales_order_items_order (sales_order_id),
    KEY idx_sales_order_items_product (product_id),
    CONSTRAINT fk_sales_order_items_order FOREIGN KEY (sales_order_id) REFERENCES sales_orders(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_sales_order_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS sales_activities (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    lead_id BIGINT UNSIGNED NOT NULL,
    opportunity_id BIGINT UNSIGNED NULL,
    sales_order_id BIGINT UNSIGNED NULL,
    campaign_id BIGINT UNSIGNED NULL,
    user_id INT NULL,
    activity_type VARCHAR(60) NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    metadata_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sales_activities_lead_created (lead_id, created_at),
    KEY idx_sales_activities_opportunity (opportunity_id),
    KEY idx_sales_activities_order (sales_order_id),
    CONSTRAINT fk_sales_activities_lead FOREIGN KEY (lead_id) REFERENCES email_leads(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_sales_activities_opportunity FOREIGN KEY (opportunity_id) REFERENCES opportunities(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_sales_activities_order FOREIGN KEY (sales_order_id) REFERENCES sales_orders(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_sales_activities_campaign FOREIGN KEY (campaign_id) REFERENCES email_sales_campaigns(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_sales_activities_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS admin_audit_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NULL,
    action VARCHAR(80) NOT NULL,
    entity_type VARCHAR(60) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    before_json LONGTEXT NULL,
    after_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_admin_audit_entity (entity_type, entity_id, created_at),
    KEY idx_admin_audit_user (user_id, created_at),
    CONSTRAINT fk_admin_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

foreach ([
    'opened_at' => 'DATETIME NULL AFTER sent_at',
    'clicked_at' => 'DATETIME NULL AFTER opened_at',
    'bounced_at' => 'DATETIME NULL AFTER clicked_at',
    'replied_at' => 'DATETIME NULL AFTER bounced_at',
    'external_message_id' => 'VARCHAR(255) NULL AFTER replied_at'
] as $name => $definition) {
    if (!crmColumnExists($pdo, 'email_sales_recipients', $name)) {
        $pdo->exec("ALTER TABLE email_sales_recipients ADD COLUMN `{$name}` {$definition}");
    }
}
if (!crmColumnExists($pdo, 'email_sales_campaigns', 'recipient_filter_json')) {
    $pdo->exec('ALTER TABLE email_sales_campaigns ADD COLUMN recipient_filter_json LONGTEXT NULL AFTER lead_import_id');
}

if (!crmColumnExists($pdo, 'transactions', 'sales_order_id')) {
    $pdo->exec('ALTER TABLE transactions MODIFY order_id INT NULL');
    $pdo->exec('ALTER TABLE transactions ADD COLUMN sales_order_id BIGINT UNSIGNED NULL AFTER order_id, ADD COLUMN opportunity_id BIGINT UNSIGNED NULL AFTER sales_order_id, ADD COLUMN lead_id BIGINT UNSIGNED NULL AFTER opportunity_id, ADD COLUMN originating_campaign_id BIGINT UNSIGNED NULL AFTER lead_id');
}
foreach ([
    'uq_transactions_sales_order' => '(sales_order_id)',
    'idx_transactions_opportunity' => '(opportunity_id)',
    'idx_transactions_lead' => '(lead_id)',
    'idx_transactions_campaign' => '(originating_campaign_id)'
] as $name => $columns) {
    if (!crmIndexExists($pdo, 'transactions', $name)) {
        $type = str_starts_with($name, 'uq_') ? 'UNIQUE KEY' : 'KEY';
        $pdo->exec("ALTER TABLE transactions ADD {$type} {$name} {$columns}");
    }
}
foreach ([
    'fk_transactions_sales_order' => ['sales_order_id', 'sales_orders'],
    'fk_transactions_opportunity' => ['opportunity_id', 'opportunities'],
    'fk_transactions_lead' => ['lead_id', 'email_leads'],
    'fk_transactions_campaign' => ['originating_campaign_id', 'email_sales_campaigns']
] as $name => [$column, $table]) {
    if (!crmConstraintExists($pdo, 'transactions', $name)) {
        $pdo->exec("ALTER TABLE transactions ADD CONSTRAINT {$name} FOREIGN KEY ({$column}) REFERENCES {$table}(id) ON UPDATE CASCADE ON DELETE RESTRICT");
    }
}

echo "Sales CRM migration complete.\n";
