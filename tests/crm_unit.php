<?php

declare(strict_types=1);

require_once __DIR__ . '/../admin/api/crm_service.php';
require_once __DIR__ . '/../config/env.php';

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$check(CrmService::money('10.5') === '10.50', 'Currency normalization failed.');
$check(in_array('Qualified', CrmService::LEAD_STATUSES, true), 'Qualified lead status is missing.');
$check(in_array('Lost', CrmService::OPPORTUNITY_STAGES, true), 'Lost opportunity stage is missing.');
$check(in_array('Paid', CrmService::ORDER_STATUSES, true), 'Paid sales-order status is missing.');
try {
    CrmService::money('-1');
    $failures[] = 'Negative currency was accepted.';
} catch (InvalidArgumentException) {
}
try {
    CrmService::money('1.999');
    $failures[] = 'Currency with more than two decimals was accepted.';
} catch (InvalidArgumentException) {
}

if (getenv('NORMAN_CRM_SCHEMA_TEST') === '1') {
    $pdo = normanCreateDatabaseConnection('admin');
    foreach (['opportunities', 'opportunity_items', 'sales_orders', 'sales_order_items', 'sales_activities', 'admin_audit_log'] as $table) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table');
        $stmt->execute([':table' => $table]);
        $check((int) $stmt->fetchColumn() === 1, "Missing CRM table: {$table}.");
    }
    foreach (['lead_status', 'lead_source', 'assigned_user_id', 'qualified_at', 'archived_at'] as $column) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_leads' AND COLUMN_NAME=:column");
        $stmt->execute([':column' => $column]);
        $check((int) $stmt->fetchColumn() === 1, "Missing email_leads column: {$column}.");
    }
    foreach (['lead_id', 'opportunity_id'] as $column) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tasks' AND COLUMN_NAME=:column");
        $stmt->execute([':column' => $column]);
        $check((int) $stmt->fetchColumn() === 1, "Missing tasks CRM relationship column: {$column}.");
    }
    $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transactions' AND INDEX_NAME='uq_transactions_sales_order' AND NON_UNIQUE=0");
    $check((int) $stmt->fetchColumn() >= 1, 'Idempotency unique index is missing.');
    $stmt = $pdo->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transactions' AND COLUMN_NAME='order_id'");
    $check($stmt->fetchColumn() === 'YES', 'Storefront order_id must be nullable for CRM transactions.');
}

if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

echo "CRM unit checks passed.\n";
