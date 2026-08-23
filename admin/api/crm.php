<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/crm_service.php';
require_once __DIR__ . '/task_helpers.php';

header('Content-Type: application/json; charset=UTF-8');

function crmJson(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function crmCsrf(): string
{
    if (empty($_SESSION['crm_csrf'])) $_SESSION['crm_csrf'] = bin2hex(random_bytes(32));
    return (string) $_SESSION['crm_csrf'];
}

function crmRequireCsrf(): void
{
    $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');
    if ($token === '' || !hash_equals(crmCsrf(), $token)) throw new RuntimeException('Your session expired. Refresh the page and try again.', 403);
}

function crmPositiveId(mixed $value, string $label): int
{
    if (!ctype_digit((string) $value) || (int) $value < 1) throw new InvalidArgumentException("Choose a valid {$label}.");
    return (int) $value;
}

function crmPaging(): array
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = max(10, min(100, (int) ($_GET['per_page'] ?? 25)));
    return [$page, $perPage, ($page - 1) * $perPage];
}

function crmLookupData(PDO $pdo): array
{
    return [
        'users' => $pdo->query("SELECT u.id,CONCAT(u.first_name,' ',u.last_name) name FROM users u INNER JOIN user_roles r ON r.id=u.user_role_id WHERE u.is_active=1 AND r.role_name='admin' ORDER BY u.first_name,u.last_name")->fetchAll(PDO::FETCH_ASSOC),
        'products' => $pdo->query('SELECT id,product_name,sku,isbn,price FROM products WHERE is_active=1 ORDER BY product_name')->fetchAll(PDO::FETCH_ASSOC),
        'campaigns' => $pdo->query('SELECT id,campaign_name FROM email_sales_campaigns ORDER BY id DESC LIMIT 250')->fetchAll(PDO::FETCH_ASSOC),
        'lead_statuses' => CrmService::LEAD_STATUSES,
        'lead_sources' => CrmService::LEAD_SOURCES,
        'priorities' => CrmService::PRIORITIES,
        'opportunity_stages' => CrmService::OPPORTUNITY_STAGES,
        'order_statuses' => CrmService::ORDER_STATUSES
    ];
}

function crmLeads(PDO $pdo): array
{
    [$page, $perPage, $offset] = crmPaging();
    $where = ['l.archived_at IS NULL'];
    $params = [];
    $search = trim((string) ($_GET['search'] ?? ''));
    if ($search !== '') {
        $where[] = "CONCAT_WS(' ',l.first_name,l.last_name,l.company,l.email_address,l.phone) LIKE :search";
        $params[':search'] = '%' . mb_substr($search, 0, 180) . '%';
    }
    foreach (['lead_status' => 'status', 'lead_source' => 'source', 'assigned_user_id' => 'owner'] as $column => $query) {
        $value = trim((string) ($_GET[$query] ?? ''));
        if ($value !== '' && $value !== 'all') {
            $where[] = "l.{$column}=:{$query}";
            $params[":{$query}"] = $value;
        }
    }
    $eligibility = (string) ($_GET['eligibility'] ?? 'all');
    if ($eligibility === 'eligible') $where[] = "l.status='active' AND s.id IS NULL";
    if ($eligibility === 'suppressed') $where[] = "(l.status<>'active' OR s.id IS NOT NULL)";
    $whereSql = implode(' AND ', $where);
    $count = $pdo->prepare("SELECT COUNT(*) FROM email_leads l LEFT JOIN email_suppressions s ON s.email_address=LOWER(TRIM(l.email_address)) WHERE {$whereSql}");
    $count->execute($params);
    $sql = "SELECT l.*,CONCAT(u.first_name,' ',u.last_name) assigned_user_name,s.reason suppression_reason,
        (SELECT COUNT(*) FROM opportunities o WHERE o.lead_id=l.id AND o.archived_at IS NULL) opportunity_count
        FROM email_leads l LEFT JOIN users u ON u.id=l.assigned_user_id LEFT JOIN email_suppressions s ON s.email_address=LOWER(TRIM(l.email_address))
        WHERE {$whereSql} ORDER BY l.updated_at DESC,l.id DESC LIMIT {$perPage} OFFSET {$offset}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return ['records' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'page' => $page, 'per_page' => $perPage, 'total' => (int) $count->fetchColumn()];
}

function crmLeadDetail(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare("SELECT l.*,CONCAT(u.first_name,' ',u.last_name) assigned_user_name,s.reason suppression_reason FROM email_leads l LEFT JOIN users u ON u.id=l.assigned_user_id LEFT JOIN email_suppressions s ON s.email_address=LOWER(TRIM(l.email_address)) WHERE l.id=:id");
    $stmt->execute([':id' => $id]);
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$lead) throw new InvalidArgumentException('Lead not found.');
    $opps = $pdo->prepare('SELECT * FROM opportunities WHERE lead_id=:id ORDER BY id DESC');
    $opps->execute([':id' => $id]);
    $campaigns = $pdo->prepare('SELECT r.*,c.campaign_name,t.template_name FROM email_sales_recipients r INNER JOIN email_sales_campaigns c ON c.id=r.campaign_id INNER JOIN email_sales_templates t ON t.id=c.template_id WHERE r.lead_id=:id ORDER BY r.id DESC');
    $campaigns->execute([':id' => $id]);
    $activities = $pdo->prepare("SELECT a.*,CONCAT(u.first_name,' ',u.last_name) user_name FROM sales_activities a LEFT JOIN users u ON u.id=a.user_id WHERE a.lead_id=:id UNION ALL SELECT 0,l.id,NULL,NULL,r.campaign_id,NULL,'email_sent',CONCAT('Sales campaign email ',r.status),r.subject_rendered,NULL,COALESCE(r.sent_at,r.created_at),NULL FROM email_sales_recipients r INNER JOIN email_leads l ON l.id=r.lead_id WHERE r.lead_id=:id AND NOT EXISTS(SELECT 1 FROM sales_activities a2 WHERE a2.lead_id=r.lead_id AND a2.campaign_id=r.campaign_id AND a2.activity_type='email_sent') ORDER BY created_at DESC LIMIT 250");
    $activities->execute([':id' => $id]);
    $tasks = $pdo->prepare("SELECT t.*,CONCAT(u.first_name,' ',u.last_name) assigned_to,o.opportunity_name FROM tasks t INNER JOIN users u ON u.id=t.user_id LEFT JOIN opportunities o ON o.id=t.opportunity_id WHERE t.lead_id=:id ORDER BY FIELD(t.task_status,'open','in_progress','completed','canceled'),t.due_at IS NULL,t.due_at,t.id DESC");
    $tasks->execute([':id' => $id]);
    return ['lead' => $lead, 'opportunities' => $opps->fetchAll(PDO::FETCH_ASSOC), 'campaign_history' => $campaigns->fetchAll(PDO::FETCH_ASSOC), 'tasks' => $tasks->fetchAll(PDO::FETCH_ASSOC), 'activities' => $activities->fetchAll(PDO::FETCH_ASSOC)];
}

function crmOpportunities(PDO $pdo): array
{
    [$page, $perPage, $offset] = crmPaging();
    $where = ['o.archived_at IS NULL'];
    $params = [];
    $search = trim((string) ($_GET['search'] ?? ''));
    if ($search !== '') { $where[] = "CONCAT_WS(' ',o.opportunity_name,l.first_name,l.last_name,l.company,l.email_address) LIKE :search"; $params[':search'] = '%' . mb_substr($search, 0, 180) . '%'; }
    $stage = trim((string) ($_GET['stage'] ?? ''));
    if ($stage !== '' && $stage !== 'all') { $where[] = 'o.stage=:stage'; $params[':stage'] = $stage; }
    $state = (string) ($_GET['state'] ?? 'all');
    if ($state === 'open') $where[] = "o.stage NOT IN ('Won','Lost')";
    if ($state === 'closed') $where[] = "o.stage IN ('Won','Lost')";
    $whereSql = implode(' AND ', $where);
    $count = $pdo->prepare("SELECT COUNT(*) FROM opportunities o INNER JOIN email_leads l ON l.id=o.lead_id WHERE {$whereSql}"); $count->execute($params);
    $stmt = $pdo->prepare("SELECT o.*,l.first_name,l.last_name,l.company,l.email_address,CONCAT(u.first_name,' ',u.last_name) assigned_user_name,(SELECT COUNT(*) FROM opportunity_items i WHERE i.opportunity_id=o.id) item_count,(SELECT COUNT(*) FROM sales_orders so WHERE so.opportunity_id=o.id) order_count FROM opportunities o INNER JOIN email_leads l ON l.id=o.lead_id LEFT JOIN users u ON u.id=o.assigned_user_id WHERE {$whereSql} ORDER BY o.updated_at DESC,o.id DESC LIMIT {$perPage} OFFSET {$offset}");
    $stmt->execute($params);
    return ['records' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'page' => $page, 'per_page' => $perPage, 'total' => (int) $count->fetchColumn()];
}

function crmOpportunityDetail(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare("SELECT o.*,l.first_name,l.last_name,l.company,l.email_address,l.phone,l.lead_status,CONCAT(u.first_name,' ',u.last_name) assigned_user_name,c.campaign_name FROM opportunities o INNER JOIN email_leads l ON l.id=o.lead_id LEFT JOIN users u ON u.id=o.assigned_user_id LEFT JOIN email_sales_campaigns c ON c.id=o.originating_campaign_id WHERE o.id=:id");
    $stmt->execute([':id' => $id]); $record = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$record) throw new InvalidArgumentException('Opportunity not found.');
    $items = $pdo->prepare('SELECT i.*,p.product_name,p.sku,p.isbn FROM opportunity_items i INNER JOIN products p ON p.id=i.product_id WHERE i.opportunity_id=:id ORDER BY i.id'); $items->execute([':id' => $id]);
    $orders = $pdo->prepare('SELECT id,order_number,status,grand_total,created_at,paid_at FROM sales_orders WHERE opportunity_id=:id ORDER BY id DESC'); $orders->execute([':id' => $id]);
    $activity = $pdo->prepare("SELECT a.*,CONCAT(u.first_name,' ',u.last_name) user_name FROM sales_activities a LEFT JOIN users u ON u.id=a.user_id WHERE a.opportunity_id=:id ORDER BY a.created_at DESC,a.id DESC"); $activity->execute([':id' => $id]);
    $tasks = $pdo->prepare("SELECT t.*,CONCAT(u.first_name,' ',u.last_name) assigned_to FROM tasks t INNER JOIN users u ON u.id=t.user_id WHERE t.opportunity_id=:id ORDER BY FIELD(t.task_status,'open','in_progress','completed','canceled'),t.due_at IS NULL,t.due_at,t.id DESC"); $tasks->execute([':id' => $id]);
    return ['opportunity' => $record, 'items' => $items->fetchAll(PDO::FETCH_ASSOC), 'orders' => $orders->fetchAll(PDO::FETCH_ASSOC), 'tasks' => $tasks->fetchAll(PDO::FETCH_ASSOC), 'activities' => $activity->fetchAll(PDO::FETCH_ASSOC)];
}

function crmOrders(PDO $pdo): array
{
    [$page, $perPage, $offset] = crmPaging(); $where = ['1=1']; $params = [];
    $search = trim((string) ($_GET['search'] ?? ''));
    if ($search !== '') { $where[] = "CONCAT_WS(' ',so.order_number,so.contact_name_snapshot,so.company_snapshot,so.email_snapshot) LIKE :search"; $params[':search'] = '%' . mb_substr($search, 0, 180) . '%'; }
    $status = trim((string) ($_GET['status'] ?? ''));
    if ($status !== '' && $status !== 'all') { $where[] = 'so.status=:status'; $params[':status'] = $status; }
    $whereSql = implode(' AND ', $where);
    $count = $pdo->prepare("SELECT COUNT(*) FROM sales_orders so WHERE {$whereSql}"); $count->execute($params);
    $stmt = $pdo->prepare("SELECT so.*,o.opportunity_name,CONCAT(u.first_name,' ',u.last_name) assigned_user_name,t.id transaction_id FROM sales_orders so LEFT JOIN opportunities o ON o.id=so.opportunity_id LEFT JOIN users u ON u.id=so.assigned_user_id LEFT JOIN transactions t ON t.sales_order_id=so.id WHERE {$whereSql} ORDER BY so.created_at DESC,so.id DESC LIMIT {$perPage} OFFSET {$offset}"); $stmt->execute($params);
    return ['records' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'page' => $page, 'per_page' => $perPage, 'total' => (int) $count->fetchColumn()];
}

function crmOrderDetail(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare('SELECT so.*,o.opportunity_name,l.first_name,l.last_name,c.campaign_name,t.id transaction_id,t.transaction_reference,t.payment_provider,t.processed_at FROM sales_orders so INNER JOIN email_leads l ON l.id=so.lead_id LEFT JOIN opportunities o ON o.id=so.opportunity_id LEFT JOIN email_sales_campaigns c ON c.id=so.originating_campaign_id LEFT JOIN transactions t ON t.sales_order_id=so.id WHERE so.id=:id'); $stmt->execute([':id' => $id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC); if (!$record) throw new InvalidArgumentException('Sales order not found.');
    $items = $pdo->prepare('SELECT * FROM sales_order_items WHERE sales_order_id=:id ORDER BY id'); $items->execute([':id' => $id]);
    $activity = $pdo->prepare("SELECT a.*,CONCAT(u.first_name,' ',u.last_name) user_name FROM sales_activities a LEFT JOIN users u ON u.id=a.user_id WHERE a.sales_order_id=:id ORDER BY a.created_at DESC,a.id DESC"); $activity->execute([':id' => $id]);
    return ['order' => $record, 'items' => $items->fetchAll(PDO::FETCH_ASSOC), 'activities' => $activity->fetchAll(PDO::FETCH_ASSOC)];
}

try {
    $action = (string) ($_GET['action'] ?? $_POST['action'] ?? 'bootstrap');
    $service = new CrmService($pdo, (int) getUserId());
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $payload = ['success' => true, 'csrf_token' => crmCsrf(), 'lookups' => crmLookupData($pdo)];
        if ($action === 'leads') $payload += crmLeads($pdo);
        elseif ($action === 'lead_detail') $payload += crmLeadDetail($pdo, crmPositiveId($_GET['id'] ?? null, 'lead'));
        elseif ($action === 'opportunities') $payload += crmOpportunities($pdo);
        elseif ($action === 'opportunity_detail') $payload += crmOpportunityDetail($pdo, crmPositiveId($_GET['id'] ?? null, 'opportunity'));
        elseif ($action === 'orders') $payload += crmOrders($pdo);
        elseif ($action === 'order_detail') $payload += crmOrderDetail($pdo, crmPositiveId($_GET['id'] ?? null, 'sales order'));
        else throw new InvalidArgumentException('Unknown CRM request.');
        crmJson($payload);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') crmJson(['success' => false, 'message' => 'This action requires POST.'], 405);
    crmRequireCsrf();
    switch ($action) {
        case 'save_lead':
            $id = (int) ($_POST['id'] ?? 0);
            $email = strtolower(trim((string) ($_POST['email_address'] ?? '')));
            $first = CrmService::clean($_POST['first_name'] ?? null, 100);
            if (!$first || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('First name and a valid email address are required.');
            $status = (string) ($_POST['lead_status'] ?? 'New'); if (!in_array($status, CrmService::LEAD_STATUSES, true)) throw new InvalidArgumentException('Choose a valid lead status.');
            $source = (string) ($_POST['lead_source'] ?? 'Other'); if (!in_array($source, CrmService::LEAD_SOURCES, true)) $source = 'Other';
            $duplicate = $pdo->prepare('SELECT id,first_name,last_name,company FROM email_leads WHERE email_address=:email AND id<>:id LIMIT 1'); $duplicate->execute([':email' => $email, ':id' => $id]);
            if ($possible = $duplicate->fetch(PDO::FETCH_ASSOC)) crmJson(['success' => false, 'duplicate' => $possible, 'message' => 'A lead with this email already exists. Review the existing record instead of creating a duplicate.'], 409);
            $data = [':first' => $first, ':last' => CrmService::clean($_POST['last_name'] ?? null, 100), ':company' => CrmService::clean($_POST['company'] ?? null, 180), ':email' => $email, ':phone' => CrmService::clean($_POST['phone'] ?? null, 50), ':job' => CrmService::clean($_POST['job_title'] ?? null, 140), ':source' => $source, ':status' => $status, ':owner' => (int) ($_POST['assigned_user_id'] ?? 0) ?: null, ':priority' => in_array($_POST['priority'] ?? '', CrmService::PRIORITIES, true) ? $_POST['priority'] : 'Normal', ':score' => ($_POST['qualification_score'] ?? '') === '' ? null : max(0, min(100, (int) $_POST['qualification_score'])), ':follow' => CrmService::clean($_POST['next_follow_up_at'] ?? null, 19), ':notes' => CrmService::clean($_POST['notes'] ?? null, 20000)];
            $pdo->beginTransaction();
            if ($id) {
                $before = $pdo->prepare('SELECT * FROM email_leads WHERE id=:id FOR UPDATE'); $before->execute([':id' => $id]); $beforeRow = $before->fetch(PDO::FETCH_ASSOC); if (!$beforeRow) throw new InvalidArgumentException('Lead not found.');
                $data[':id'] = $id; $pdo->prepare('UPDATE email_leads SET first_name=:first,last_name=:last,company=:company,email_address=:email,phone=:phone,job_title=:job,lead_source=:source,lead_status=:status,assigned_user_id=:owner,priority=:priority,qualification_score=:score,next_follow_up_at=:follow,notes=:notes WHERE id=:id')->execute($data);
                $service->logActivity($id, 'lead_updated', 'Lead updated'); $service->audit('lead_updated', 'lead', $id, $beforeRow, ['email_address' => $email, 'lead_status' => $status]);
            } else {
                $pdo->prepare("INSERT INTO email_leads(first_name,last_name,company,email_address,phone,job_title,lead_source,lead_status,assigned_user_id,priority,qualification_score,next_follow_up_at,notes) VALUES(:first,:last,:company,:email,:phone,:job,:source,:status,:owner,:priority,:score,:follow,:notes)")->execute($data); $id = (int) $pdo->lastInsertId();
                $service->logActivity($id, 'lead_created', 'Lead created'); $service->audit('lead_created', 'lead', $id, null, ['email_address' => $email]);
            }
            $pdo->commit(); crmJson(['success' => true, 'id' => $id, 'message' => 'Lead saved.']);
        case 'qualify_lead': $service->qualifyLead(crmPositiveId($_POST['id'] ?? null, 'lead'), $_POST['qualification_notes'] ?? null); crmJson(['success' => true, 'message' => 'Lead qualified.']);
        case 'disqualify_lead': $service->disqualifyLead(crmPositiveId($_POST['id'] ?? null, 'lead'), (string) ($_POST['reason'] ?? '')); crmJson(['success' => true, 'message' => 'Lead disqualified.']);
        case 'archive_lead':
            $id = crmPositiveId($_POST['id'] ?? null, 'lead'); $pdo->beginTransaction(); $stmt = $pdo->prepare('SELECT * FROM email_leads WHERE id=:id FOR UPDATE'); $stmt->execute([':id' => $id]); $lead = $stmt->fetch(PDO::FETCH_ASSOC); if (!$lead) throw new InvalidArgumentException('Lead not found.'); $pdo->prepare("UPDATE email_leads SET lead_status='Archived',archived_at=NOW() WHERE id=:id")->execute([':id' => $id]); $service->logActivity($id, 'lead_archived', 'Lead archived'); $service->audit('lead_archived', 'lead', $id, $lead, ['lead_status' => 'Archived']); $pdo->commit(); crmJson(['success' => true, 'message' => 'Lead archived.']);
        case 'add_note': $id = crmPositiveId($_POST['id'] ?? null, 'lead'); $note = CrmService::clean($_POST['note'] ?? null, 10000); if (!$note) throw new InvalidArgumentException('Note text is required.'); $service->logActivity($id, 'note_added', 'Note added', $note); crmJson(['success' => true, 'message' => 'Note added.']);
        case 'create_task':
            $userId = crmPositiveId($_POST['user_id'] ?? null, 'assigned user');
            if (!taskUserIsAdministrator($pdo, $userId)) throw new InvalidArgumentException('Tasks can only be assigned to administrators.');
            $leadId = taskIntOrNull($_POST['lead_id'] ?? '', 'Lead');
            $opportunityId = taskIntOrNull($_POST['opportunity_id'] ?? '', 'Opportunity');
            [$leadId, $opportunityId] = taskResolveCrmRelationship($pdo, $leadId, $opportunityId);
            if ($leadId === null) throw new InvalidArgumentException('A lead or opportunity is required.');
            $title = taskRequiredString($_POST['title'] ?? '', 'Task title');
            if (strlen($title) > 255) throw new InvalidArgumentException('Task title must be 255 characters or fewer.');
            $priority = taskAllowedValue($_POST['priority'] ?? '', 'Priority', ['low','normal','high','urgent'], 'normal');
            $dueAt = taskDateTimeOrNull($_POST['due_at'] ?? '', 'Due date');
            $description = taskStringOrNull($_POST['description'] ?? '');
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO tasks(user_id,lead_id,opportunity_id,title,description,task_status,priority,due_at) VALUES(:user,:lead,:opportunity,:title,:description,'open',:priority,:due)");
            $stmt->execute([':user' => $userId, ':lead' => $leadId, ':opportunity' => $opportunityId, ':title' => $title, ':description' => $description, ':priority' => $priority, ':due' => $dueAt]);
            $taskId = (int) $pdo->lastInsertId();
            $service->logActivity($leadId, 'task_created', 'Task created', $title, $opportunityId, null, null, ['task_id' => $taskId, 'assigned_user_id' => $userId]);
            $pdo->commit();
            crmJson(['success' => true, 'id' => $taskId, 'message' => 'Task created.']);
        case 'create_opportunity': $id = $service->createOpportunity(crmPositiveId($_POST['lead_id'] ?? null, 'lead'), $_POST); crmJson(['success' => true, 'id' => $id, 'message' => 'Opportunity created.']);
        case 'set_opportunity_stage': $service->setOpportunityStage(crmPositiveId($_POST['id'] ?? null, 'opportunity'), (string) ($_POST['stage'] ?? ''), $_POST['reason'] ?? null); crmJson(['success' => true, 'message' => 'Opportunity stage updated.']);
        case 'save_opportunity': $service->updateOpportunity(crmPositiveId($_POST['id'] ?? null, 'opportunity'), $_POST); crmJson(['success' => true, 'message' => 'Opportunity updated.']);
        case 'save_opportunity_item': $service->saveOpportunityItem(crmPositiveId($_POST['opportunity_id'] ?? null, 'opportunity'), crmPositiveId($_POST['product_id'] ?? null, 'product'), max(0, (int) ($_POST['quantity'] ?? 0)), $_POST['proposed_unit_price'] ?? '', $_POST['discount_amount'] ?? '0'); crmJson(['success' => true, 'message' => 'Opportunity product saved.']);
        case 'create_sales_order': $id = $service->createSalesOrder(crmPositiveId($_POST['opportunity_id'] ?? null, 'opportunity'), $_POST); crmJson(['success' => true, 'id' => $id, 'message' => 'Sales order created.']);
        case 'change_order_status': $service->changeOrderStatus(crmPositiveId($_POST['id'] ?? null, 'sales order'), (string) ($_POST['status'] ?? '')); crmJson(['success' => true, 'message' => 'Sales order status updated.']);
        case 'save_draft_order': $service->updateDraftOrder(crmPositiveId($_POST['id'] ?? null, 'sales order'), $_POST); crmJson(['success' => true, 'message' => 'Draft sales order updated and totals recalculated.']);
        case 'record_payment': $id = $service->recordPayment(crmPositiveId($_POST['id'] ?? null, 'sales order'), (string) ($_POST['transaction_reference'] ?? ''), $_POST['payment_provider'] ?? null); crmJson(['success' => true, 'transaction_id' => $id, 'message' => 'Payment recorded. Repeated submissions with this order/reference return the same transaction.']);
        default: throw new InvalidArgumentException('Unknown CRM action.');
    }
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $status = $e instanceof InvalidArgumentException ? 422 : (($e->getCode() === 403) ? 403 : 500);
    if ($status === 500) error_log('CRM request failed: ' . $e->getMessage());
    crmJson(['success' => false, 'message' => $status === 500 ? 'The CRM action could not be completed.' : $e->getMessage()], $status);
}
