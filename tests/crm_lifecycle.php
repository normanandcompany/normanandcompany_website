<?php

declare(strict_types=1);

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../admin/api/crm_service.php';
require_once __DIR__ . '/../admin/api/task_helpers.php';
require_once __DIR__ . '/../classes/Email/EmailToken.php';
require_once __DIR__ . '/../classes/Email/EmailQueueService.php';

if (getenv('NORMAN_EMAIL_TOKEN_KEY') === false) {
    putenv('NORMAN_EMAIL_TOKEN_KEY=crm-lifecycle-test-key-not-for-production');
}

$pdo = normanCreateDatabaseConnection('admin');
$adminId = (int) $pdo->query("SELECT u.id FROM users u INNER JOIN user_roles r ON r.id=u.user_role_id WHERE r.role_name='admin' AND u.is_active=1 ORDER BY u.id LIMIT 1")->fetchColumn();
$product = $pdo->query("SELECT id,price FROM products WHERE is_active=1 ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$adminId || !$product) {
    fwrite(STDERR, "Lifecycle test requires one active administrator and one active product.\n");
    exit(1);
}

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$pdo->beginTransaction();
try {
    $email = 'crm-test-' . bin2hex(random_bytes(6)) . '@example.invalid';
    $pdo->prepare("INSERT INTO email_leads(first_name,last_name,company,email_address,lead_status,lead_source,assigned_user_id) VALUES('CRM','Test','Norman Test Organization',:email,'New','Manual Entry',:user)")
        ->execute([':email' => $email, ':user' => $adminId]);
    $leadId = (int) $pdo->lastInsertId();
    $service = new CrmService($pdo, $adminId);

    $service->qualifyLead($leadId, 'Lifecycle verification');
    $check($pdo->query("SELECT lead_status FROM email_leads WHERE id={$leadId}")->fetchColumn() === 'Qualified', 'Lead was not qualified.');

    $opportunityId = $service->createOpportunity($leadId, ['opportunity_name' => 'Lifecycle Test Opportunity', 'estimated_value' => '0', 'probability' => 35]);
    $service->updateOpportunity($opportunityId, ['opportunity_name' => 'Updated Lifecycle Opportunity', 'assigned_user_id' => $adminId, 'probability' => 45, 'expected_close_date' => date('Y-m-d', strtotime('+30 days')), 'notes' => 'Updated in lifecycle test']);
    $service->saveOpportunityItem($opportunityId, (int) $product['id'], 2, (string) $product['price'], '1.00');
    $service->setOpportunityStage($opportunityId, 'Proposal');

    [$resolvedLeadId, $resolvedOpportunityId] = taskResolveCrmRelationship($pdo, null, $opportunityId);
    $check($resolvedLeadId === $leadId && $resolvedOpportunityId === $opportunityId, 'Opportunity task relationship did not resolve its parent lead.');

    $pdo->prepare("INSERT INTO tasks(user_id,lead_id,opportunity_id,title,task_status,priority) VALUES(:user,:lead,:opportunity,'Lifecycle follow-up','open','normal')")
        ->execute([':user' => $adminId, ':lead' => $leadId, ':opportunity' => $opportunityId]);
    $taskId = (int) $pdo->lastInsertId();
    $taskRelation = $pdo->query("SELECT lead_id,opportunity_id FROM tasks WHERE id={$taskId}")->fetch(PDO::FETCH_ASSOC);
    $check((int) ($taskRelation['lead_id'] ?? 0) === $leadId && (int) ($taskRelation['opportunity_id'] ?? 0) === $opportunityId, 'Task was not linked to its lead and opportunity.');

    $pdo->prepare("INSERT INTO email_sales_templates(template_name,subject_template,body_template,created_by_user_id) VALUES('CRM Lifecycle Template','Lifecycle {Company}','Test message',:user)")->execute([':user' => $adminId]);
    $templateId = (int) $pdo->lastInsertId();
    $filters = json_encode(['audience' => 'filtered', 'lead_status' => 'Qualified', 'lead_source' => 'Manual Entry', 'opportunity_state' => 'open']);
    $pdo->prepare("INSERT INTO email_sales_campaigns(campaign_name,template_id,recipient_filter_json,created_by_user_id) VALUES('CRM Lifecycle Campaign',:template,:filters,:user)")->execute([':template' => $templateId, ':filters' => $filters, ':user' => $adminId]);
    $campaignId = (int) $pdo->lastInsertId();
    $queued = (new EmailQueueService($pdo))->queueSalesCampaign($campaignId);
    $recipient = $pdo->prepare('SELECT COUNT(*) FROM email_sales_recipients WHERE campaign_id=:campaign AND lead_id=:lead');
    $recipient->execute([':campaign' => $campaignId, ':lead' => $leadId]);
    $check($queued >= 1 && (int) $recipient->fetchColumn() === 1, 'Filtered Sales Campaign did not queue the eligible Lead.');

    $orderId = $service->createSalesOrder($opportunityId, ['tax_total' => '2.50', 'shipping_total' => '4.00', 'internal_notes' => 'Rollback-only lifecycle test']);
    $service->updateDraftOrder($orderId, ['tax_total' => '3.50', 'shipping_total' => '4.00', 'billing_address_snapshot' => 'Test billing address', 'shipping_address_snapshot' => 'Test shipping address', 'internal_notes' => 'Updated rollback-only lifecycle test']);

    $order = $pdo->query("SELECT * FROM sales_orders WHERE id={$orderId}")->fetch(PDO::FETCH_ASSOC);
    $check((bool) preg_match('/^SO-\d{4}-\d{6}$/', (string) $order['order_number']), 'Sales order number format is invalid.');
    $expected = ((float) $product['price'] * 2) - 1 + 3.5 + 4;
    $check(abs((float) $order['grand_total'] - $expected) < 0.001, 'Server-side sales order total is incorrect.');
    $snapshot = $pdo->query("SELECT product_name_snapshot,unit_price,quantity FROM sales_order_items WHERE sales_order_id={$orderId}")->fetch(PDO::FETCH_ASSOC);
    $check((bool) $snapshot && (int) $snapshot['quantity'] === 2, 'Sales-order item snapshot was not created.');

    try {
        $service->changeOrderStatus($orderId, 'Paid');
        $failures[] = 'Invalid Draft-to-Paid transition was accepted.';
    } catch (InvalidArgumentException) {
    }
    $service->changeOrderStatus($orderId, 'Ready');
    $service->changeOrderStatus($orderId, 'Awaiting Payment');
    $reference = 'crm-test-' . bin2hex(random_bytes(6));
    $firstTransaction = $service->recordPayment($orderId, $reference, 'test');
    $secondTransaction = $service->recordPayment($orderId, $reference, 'test');
    $check($firstTransaction === $secondTransaction, 'Payment retry returned a different transaction.');
    $check((int) $pdo->query("SELECT COUNT(*) FROM transactions WHERE sales_order_id={$orderId}")->fetchColumn() === 1, 'Payment retry created a duplicate transaction.');
    $check($pdo->query("SELECT status FROM sales_orders WHERE id={$orderId}")->fetchColumn() === 'Paid', 'Sales order did not become Paid.');
    $check($pdo->query("SELECT stage FROM opportunities WHERE id={$opportunityId}")->fetchColumn() === 'Won', 'Opportunity did not become Won.');
    $check($pdo->query("SELECT lead_status FROM email_leads WHERE id={$leadId}")->fetchColumn() === 'Converted', 'Lead did not become Converted.');
    $check((int) $pdo->query("SELECT COUNT(*) FROM sales_activities WHERE lead_id={$leadId}")->fetchColumn() >= 7, 'Lifecycle activity timeline is incomplete.');

    $pdo->prepare("INSERT INTO email_suppressions(email_address,reason,source) VALUES(:email,'manual','crm_test')")->execute([':email' => $email]);
    $eligible = $pdo->prepare("SELECT COUNT(*) FROM email_leads l LEFT JOIN email_suppressions s ON s.email_address=LOWER(TRIM(l.email_address)) WHERE l.id=:id AND l.status='active' AND s.id IS NULL");
    $eligible->execute([':id' => $leadId]);
    $check((int) $eligible->fetchColumn() === 0, 'Suppressed lead remained campaign-eligible.');
} finally {
    $pdo->rollBack();
}

if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

echo "CRM lifecycle checks passed; all test records rolled back.\n";
