<?php

require_once 'task_helpers.php';
requireAdminTaskJson();
require_once 'db.php';

try {
    $leads = $pdo->query("SELECT id,first_name,last_name,company,email_address FROM email_leads WHERE archived_at IS NULL ORDER BY company,first_name,last_name,id DESC")
        ->fetchAll(PDO::FETCH_ASSOC);
    $opportunities = $pdo->query("SELECT o.id,o.lead_id,o.opportunity_name,o.stage,CONCAT_WS(' ',l.first_name,l.last_name) lead_name,l.company FROM opportunities o INNER JOIN email_leads l ON l.id=o.lead_id WHERE o.archived_at IS NULL ORDER BY o.opportunity_name,o.id DESC")
        ->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['leads' => $leads, 'opportunities' => $opportunities]);
} catch (Throwable $e) {
    sendTaskJson(['success' => false, 'message' => $e->getMessage()], 500);
}
