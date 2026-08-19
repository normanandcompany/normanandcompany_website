<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../../classes/Email/EmailHtml.php';
require_once __DIR__ . '/../../classes/Email/EmailConfig.php';
require_once __DIR__ . '/../../classes/Email/EmailToken.php';
require_once __DIR__ . '/../../classes/Email/SmtpMailer.php';
require_once __DIR__ . '/../../classes/Email/EmailQueueService.php';

header('Content-Type: application/json; charset=UTF-8');

function emailToolsJson(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function emailToolsCsrf(): string
{
    if (empty($_SESSION['email_tools_csrf'])) {
        $_SESSION['email_tools_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['email_tools_csrf'];
}

function emailToolsRequireCsrf(): void
{
    $token = (string) ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($token === '' || !hash_equals(emailToolsCsrf(), $token)) {
        throw new RuntimeException('Your session expired. Refresh Email Tools and try again.', 403);
    }
}

function emailToolsText(string $key, int $max): string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    if ($value === '') {
        throw new InvalidArgumentException(ucwords(str_replace('_', ' ', $key)) . ' is required.');
    }
    return mb_substr($value, 0, $max);
}

function emailToolsBootstrap(PDO $pdo): array
{
    $newsletterSql = "SELECT n.id,n.newsletter_name,n.subject,n.status,n.created_at,n.queued_at,n.completed_at,t.template_name,
        COUNT(r.id) recipient_count,SUM(r.status='sent') sent_count,SUM(r.status='failed') failed_count,
        SUM(r.status IN ('skipped','unsubscribed')) skipped_count,SUM(r.status='pending') pending_count
        FROM email_newsletters n LEFT JOIN email_newsletter_templates t ON t.id=n.template_id
        LEFT JOIN email_newsletter_recipients r ON r.newsletter_id=n.id
        GROUP BY n.id ORDER BY n.id DESC LIMIT 100";
    $campaignSql = "SELECT c.id,c.campaign_name,c.status,c.queued_at,c.completed_at,t.template_name,i.import_name,
        COUNT(r.id) recipient_count,SUM(r.status='sent') sent_count,SUM(r.status='failed') failed_count,
        SUM(r.status IN ('skipped','unsubscribed')) skipped_count,SUM(r.status='pending') pending_count
        FROM email_sales_campaigns c INNER JOIN email_sales_templates t ON t.id=c.template_id
        LEFT JOIN email_lead_imports i ON i.id=c.lead_import_id
        LEFT JOIN email_sales_recipients r ON r.campaign_id=c.id GROUP BY c.id ORDER BY c.id DESC LIMIT 100";
    $leadImports = $pdo->query("SELECT i.id,i.import_name,i.source_filename,i.imported_count,i.invalid_count,i.created_at,
        COUNT(m.lead_id) lead_count FROM email_lead_imports i
        LEFT JOIN email_lead_import_members m ON m.import_id=i.id
        GROUP BY i.id ORDER BY i.id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    $leads = $pdo->query("SELECT id,first_name,last_name,company,email_address,status,total_emails_sent,last_contacted_at
        FROM email_leads ORDER BY id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    $templates = $pdo->query('SELECT t.id,t.template_name,t.subject_template,t.body_template,t.signature_id,t.updated_at,s.signature_name
        FROM email_sales_templates t LEFT JOIN email_signatures s ON s.id=t.signature_id ORDER BY t.template_name')->fetchAll(PDO::FETCH_ASSOC);
    $newsletterTemplates = $pdo->query('SELECT id,template_name,subject_template,html_body,updated_at FROM email_newsletter_templates ORDER BY template_name')->fetchAll(PDO::FETCH_ASSOC);
    $signatures = $pdo->query('SELECT id,signature_name,is_default,updated_at FROM email_signatures ORDER BY is_default DESC,signature_name')->fetchAll(PDO::FETCH_ASSOC);
    $metrics = $pdo->query("SELECT
        (SELECT COUNT(*) FROM email_leads) lead_count,
        (SELECT COUNT(*) FROM email_leads WHERE status='active') active_lead_count,
        (SELECT COUNT(*) FROM email_sales_campaigns WHERE status='completed') completed_campaign_count,
        (SELECT COUNT(*) FROM email_delivery_log WHERE message_type='sales' AND status='sent') sales_sent_count,
        (SELECT COUNT(*) FROM email_suppressions) suppression_count")->fetch(PDO::FETCH_ASSOC);
    $config = new EmailConfig($pdo);
    return [
        'success' => true,
        'csrf_token' => emailToolsCsrf(),
        'smtp_configured' => $config->isReady(),
        'email_configuration' => $config->publicValues(),
        'newsletter_templates' => $newsletterTemplates,
        'newsletters' => $pdo->query($newsletterSql)->fetchAll(PDO::FETCH_ASSOC),
        'campaigns' => $pdo->query($campaignSql)->fetchAll(PDO::FETCH_ASSOC),
        'lead_imports' => $leadImports,
        'leads' => $leads,
        'templates' => $templates,
        'signatures' => $signatures,
        'metrics' => $metrics
    ];
}

try {
    $action = (string) ($_GET['action'] ?? $_POST['action'] ?? 'bootstrap');
    if ($action === 'bootstrap') {
        emailToolsJson(emailToolsBootstrap($pdo));
    }
    emailToolsRequireCsrf();
    $userId = (int) getUserId();

    switch ($action) {
        case 'save_newsletter_template':
            $templateId = (int) ($_POST['id'] ?? 0);
            $name = emailToolsText('template_name', 180);
            $subject = emailToolsText('subject', 255);
            $html = EmailHtml::sanitize((string) ($_POST['html_body'] ?? ''));
            if ($html === '' || EmailHtml::textVersion($html) === '') {
                throw new InvalidArgumentException('Newsletter body is required.');
            }
            if ($templateId) {
                $stmt = $pdo->prepare('UPDATE email_newsletter_templates SET template_name=:name,subject_template=:subject,html_body=:body WHERE id=:id');
                $stmt->execute([':name' => $name, ':subject' => $subject, ':body' => $html, ':id' => $templateId]);
                if ($stmt->rowCount() === 0) {
                    $exists = $pdo->prepare('SELECT 1 FROM email_newsletter_templates WHERE id=:id');
                    $exists->execute([':id' => $templateId]);
                    if (!$exists->fetchColumn()) {
                        throw new InvalidArgumentException('The newsletter template no longer exists.');
                    }
                }
                emailToolsJson(['success' => true, 'message' => 'Newsletter template updated.']);
            }
            $stmt = $pdo->prepare('INSERT INTO email_newsletter_templates(template_name,subject_template,html_body,created_by_user_id) VALUES(:name,:subject,:body,:user)');
            $stmt->execute([':name' => $name, ':subject' => $subject, ':body' => $html, ':user' => $userId]);
            emailToolsJson(['success' => true, 'message' => 'Newsletter template saved.']);

        case 'delete_newsletter_template':
            $templateId = (int) ($_POST['id'] ?? 0);
            if (!$templateId) {
                throw new InvalidArgumentException('Choose a newsletter template to delete.');
            }
            $used = $pdo->prepare('SELECT COUNT(*) FROM email_newsletters WHERE template_id=:id');
            $used->execute([':id' => $templateId]);
            if ((int) $used->fetchColumn() > 0) {
                throw new InvalidArgumentException('This template is attached to newsletter delivery history and cannot be deleted. You can edit it instead.');
            }
            $delete = $pdo->prepare('DELETE FROM email_newsletter_templates WHERE id=:id');
            $delete->execute([':id' => $templateId]);
            if ($delete->rowCount() !== 1) {
                throw new InvalidArgumentException('The newsletter template no longer exists.');
            }
            emailToolsJson(['success' => true, 'message' => 'Newsletter template deleted.']);

        case 'send_newsletter':
            $name = emailToolsText('newsletter_name', 180);
            $templateId = (int) ($_POST['newsletter_template_id'] ?? 0);
            $template = $pdo->prepare('SELECT * FROM email_newsletter_templates WHERE id=:id');
            $template->execute([':id' => $templateId]);
            $templateRecord = $template->fetch(PDO::FETCH_ASSOC);
            if (!$templateRecord) {
                throw new InvalidArgumentException('Choose a valid newsletter template.');
            }
            $pdo->prepare('INSERT INTO email_newsletters(template_id,newsletter_name,subject,html_body,created_by_user_id) VALUES(:template,:name,:subject,:body,:user)')
                ->execute([':template' => $templateId, ':name' => $name, ':subject' => $templateRecord['subject_template'], ':body' => $templateRecord['html_body'], ':user' => $userId]);
            $id = (int) $pdo->lastInsertId();
            $count = (new EmailQueueService($pdo))->queueNewsletter($id);
            emailToolsJson(['success' => true, 'message' => "Newsletter queued for {$count} customer(s)."]);

        case 'save_email_configuration':
            (new EmailConfig($pdo))->save($_POST, $userId);
            emailToolsJson(['success' => true, 'message' => 'Email configuration saved securely.']);

        case 'save_signature':
            $name = emailToolsText('signature_name', 120);
            $html = EmailHtml::sanitize((string) ($_POST['html_body'] ?? ''));
            if ($html === '') {
                throw new InvalidArgumentException('HTML signature is required.');
            }
            $default = isset($_POST['is_default']) ? 1 : 0;
            $pdo->beginTransaction();
            if ($default) {
                $pdo->exec('UPDATE email_signatures SET is_default=0');
            }
            $pdo->prepare('INSERT INTO email_signatures(signature_name,html_body,is_default,created_by_user_id) VALUES(:name,:body,:default,:user)')
                ->execute([':name' => $name, ':body' => $html, ':default' => $default, ':user' => $userId]);
            $pdo->commit();
            emailToolsJson(['success' => true, 'message' => 'Email signature saved.']);

        case 'save_template':
            $templateId = (int) ($_POST['id'] ?? 0);
            $name = emailToolsText('template_name', 180);
            $subject = emailToolsText('subject_template', 255);
            $body = emailToolsText('body_template', 20000);
            $signatureId = (int) ($_POST['signature_id'] ?? 0) ?: null;
            if ($signatureId) {
                $check = $pdo->prepare('SELECT 1 FROM email_signatures WHERE id=:id');
                $check->execute([':id' => $signatureId]);
                if (!$check->fetchColumn()) {
                    throw new InvalidArgumentException('Choose a valid signature.');
                }
            }
            if ($templateId) {
                $update = $pdo->prepare('UPDATE email_sales_templates SET template_name=:name,subject_template=:subject,body_template=:body,signature_id=:signature WHERE id=:id');
                $update->execute([':name' => $name, ':subject' => $subject, ':body' => $body, ':signature' => $signatureId, ':id' => $templateId]);
                if ($update->rowCount() === 0) {
                    $exists = $pdo->prepare('SELECT 1 FROM email_sales_templates WHERE id=:id');
                    $exists->execute([':id' => $templateId]);
                    if (!$exists->fetchColumn()) {
                        throw new InvalidArgumentException('The sales template no longer exists.');
                    }
                }
                emailToolsJson(['success' => true, 'message' => 'Sales template updated.']);
            }
            $pdo->prepare('INSERT INTO email_sales_templates(template_name,subject_template,body_template,signature_id,created_by_user_id) VALUES(:name,:subject,:body,:signature,:user)')
                ->execute([':name' => $name, ':subject' => $subject, ':body' => $body, ':signature' => $signatureId, ':user' => $userId]);
            emailToolsJson(['success' => true, 'message' => 'Sales template saved.']);

        case 'delete_template':
            $templateId = (int) ($_POST['id'] ?? 0);
            if (!$templateId) {
                throw new InvalidArgumentException('Choose a sales template to delete.');
            }
            $used = $pdo->prepare('SELECT COUNT(*) FROM email_sales_campaigns WHERE template_id=:id');
            $used->execute([':id' => $templateId]);
            if ((int) $used->fetchColumn() > 0) {
                throw new InvalidArgumentException('This template is attached to campaign history and cannot be deleted. You can edit it instead.');
            }
            $delete = $pdo->prepare('DELETE FROM email_sales_templates WHERE id=:id');
            $delete->execute([':id' => $templateId]);
            if ($delete->rowCount() !== 1) {
                throw new InvalidArgumentException('The sales template no longer exists.');
            }
            emailToolsJson(['success' => true, 'message' => 'Sales template deleted.']);

        case 'import_leads':
            $importName = emailToolsText('import_name', 180);
            $file = $_FILES['leads_csv'] ?? null;
            if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException('Choose a CSV file to import.');
            }
            if ((int) $file['size'] > 5 * 1024 * 1024) {
                throw new InvalidArgumentException('CSV files must be 5 MB or smaller.');
            }
            $handle = fopen($file['tmp_name'], 'rb');
            if (!$handle) {
                throw new RuntimeException('The uploaded CSV could not be opened.');
            }
            $header = fgetcsv($handle);
            $normalized = array_map(fn($value) => strtolower(preg_replace('/[^a-z]/i', '', (string) $value)), $header ?: []);
            $required = ['firstname', 'lastname', 'company', 'emailaddress'];
            $columns = array_flip($normalized);
            foreach ($required as $column) {
                if (!isset($columns[$column])) {
                    fclose($handle);
                    throw new InvalidArgumentException('CSV header must contain FirstName, LastName, Company, and EmailAddress.');
                }
            }
            $sourceFilename = mb_substr(basename((string) ($file['name'] ?? 'leads.csv')), 0, 255);
            $pdo->beginTransaction();
            $createImport = $pdo->prepare('INSERT INTO email_lead_imports(import_name,source_filename,created_by_user_id) VALUES(:name,:filename,:user)');
            $createImport->execute([':name' => $importName, ':filename' => $sourceFilename, ':user' => $userId]);
            $importId = (int) $pdo->lastInsertId();
            $upsert = $pdo->prepare("INSERT INTO email_leads(first_name,last_name,company,email_address) VALUES(:first,:last,:company,:email)
                ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),first_name=VALUES(first_name),last_name=VALUES(last_name),company=VALUES(company),updated_at=NOW()");
            $addMember = $pdo->prepare('INSERT IGNORE INTO email_lead_import_members(import_id,lead_id) VALUES(:import,:lead)');
            $imported = 0;
            $invalid = 0;
            while (($row = fgetcsv($handle)) !== false) {
                $email = strtolower(trim((string) ($row[$columns['emailaddress']] ?? '')));
                $first = trim((string) ($row[$columns['firstname']] ?? ''));
                if ($first === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $invalid++;
                    continue;
                }
                $upsert->execute([
                    ':first' => mb_substr($first, 0, 100),
                    ':last' => mb_substr(trim((string) ($row[$columns['lastname']] ?? '')), 0, 100),
                    ':company' => mb_substr(trim((string) ($row[$columns['company']] ?? '')), 0, 180),
                    ':email' => $email
                ]);
                $leadId = (int) $pdo->lastInsertId();
                $addMember->execute([':import' => $importId, ':lead' => $leadId]);
                $imported += $addMember->rowCount();
            }
            fclose($handle);
            if ($imported === 0) {
                throw new InvalidArgumentException('The CSV did not contain any valid, unique leads.');
            }
            $pdo->prepare('UPDATE email_lead_imports SET imported_count=:imported,invalid_count=:invalid WHERE id=:id')
                ->execute([':imported' => $imported, ':invalid' => $invalid, ':id' => $importId]);
            $pdo->commit();
            emailToolsJson(['success' => true, 'message' => "Created {$importName} with {$imported} lead(s); skipped {$invalid} invalid row(s)."]);

        case 'send_campaign':
            $name = emailToolsText('campaign_name', 180);
            $templateId = (int) ($_POST['template_id'] ?? 0);
            $leadImportId = (int) ($_POST['lead_import_id'] ?? 0);
            $check = $pdo->prepare('SELECT 1 FROM email_sales_templates WHERE id=:id');
            $check->execute([':id' => $templateId]);
            if (!$check->fetchColumn()) {
                throw new InvalidArgumentException('Choose a valid email template.');
            }
            $checkImport = $pdo->prepare('SELECT 1 FROM email_lead_imports WHERE id=:id');
            $checkImport->execute([':id' => $leadImportId]);
            if (!$checkImport->fetchColumn()) {
                throw new InvalidArgumentException('Choose a valid CSV import.');
            }
            $pdo->prepare('INSERT INTO email_sales_campaigns(campaign_name,template_id,lead_import_id,created_by_user_id) VALUES(:name,:template,:import,:user)')
                ->execute([':name' => $name, ':template' => $templateId, ':import' => $leadImportId, ':user' => $userId]);
            $campaignId = (int) $pdo->lastInsertId();
            $count = (new EmailQueueService($pdo))->queueSalesCampaign($campaignId);
            emailToolsJson(['success' => true, 'message' => "Campaign queued for {$count} lead(s)."]);

        default:
            emailToolsJson(['success' => false, 'message' => 'Unknown Email Tools action.'], 404);
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $status = $e->getCode() === 403 ? 403 : ($e instanceof InvalidArgumentException ? 422 : 500);
    if ($status === 500) {
        error_log('Email Tools API error: ' . $e->getMessage());
    }
    emailToolsJson(['success' => false, 'message' => $status === 500 ? 'Email Tools could not complete the request. Check the server error log.' : $e->getMessage()], $status);
}
