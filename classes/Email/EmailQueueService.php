<?php

declare(strict_types=1);

final class EmailQueueService
{
    public function __construct(private PDO $pdo, private ?SmtpMailer $mailer = null, private array $config = [])
    {
    }

    public function queueNewsletter(int $newsletterId): int
    {
        $this->pdo->beginTransaction();
        try {
            $newsletter = $this->lockedRecord('email_newsletters', $newsletterId);
            if (!$newsletter || !in_array($newsletter['status'], ['draft', 'paused'], true)) {
                throw new InvalidArgumentException('Only draft or paused newsletters can be queued.');
            }

            $customers = $this->pdo->query("SELECT u.id,u.first_name,u.last_name,LOWER(TRIM(u.email_address)) email_address
                FROM users u INNER JOIN user_roles r ON r.id=u.user_role_id
                LEFT JOIN email_suppressions s ON s.email_address=LOWER(TRIM(u.email_address))
                WHERE r.role_name='customer' AND u.is_active=1 AND COALESCE(u.visible,1)=1
                  AND s.id IS NULL AND u.email_address IS NOT NULL AND TRIM(u.email_address)<>''")->fetchAll(PDO::FETCH_ASSOC);

            $insert = $this->pdo->prepare("INSERT IGNORE INTO email_newsletter_recipients
                (newsletter_id,user_id,email_address,first_name,last_name,unsubscribe_token_hash)
                VALUES(:newsletter,:user,:email,:first,:last,:token_hash)");
            $updateToken = $this->pdo->prepare('UPDATE email_newsletter_recipients SET unsubscribe_token_hash=:hash WHERE id=:id');
            $count = 0;
            foreach ($customers as $customer) {
                if (!filter_var($customer['email_address'], FILTER_VALIDATE_EMAIL)) {
                    continue;
                }
                $placeholder = hash('sha256', random_bytes(32));
                $insert->execute([':newsletter' => $newsletterId, ':user' => $customer['id'], ':email' => $customer['email_address'], ':first' => $customer['first_name'], ':last' => $customer['last_name'], ':token_hash' => $placeholder]);
                if ($insert->rowCount() === 1) {
                    $recipientId = (int) $this->pdo->lastInsertId();
                    $token = EmailToken::make('newsletter', $recipientId, $customer['email_address']);
                    $updateToken->execute([':hash' => hash('sha256', $token), ':id' => $recipientId]);
                    $count++;
                }
            }
            if ($count === 0 && $newsletter['status'] === 'draft') {
                throw new RuntimeException('No eligible customer email addresses were found.');
            }
            $this->pdo->prepare("UPDATE email_newsletters SET status='queued',queued_at=COALESCE(queued_at,NOW()),completed_at=NULL WHERE id=:id")
                ->execute([':id' => $newsletterId]);
            $this->pdo->commit();
            return $count;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function queueSalesCampaign(int $campaignId): int
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) $this->pdo->beginTransaction();
        try {
            $campaign = $this->lockedRecord('email_sales_campaigns', $campaignId);
            if (!$campaign || !in_array($campaign['status'], ['draft', 'paused'], true)) {
                throw new InvalidArgumentException('Only draft or paused campaigns can be queued.');
            }
            $leadImportId = (int) ($campaign['lead_import_id'] ?? 0);
            $filters = json_decode((string) ($campaign['recipient_filter_json'] ?? ''), true) ?: [];
            $audience = in_array($filters['audience'] ?? '', ['all', 'import', 'filtered'], true) ? $filters['audience'] : ($leadImportId ? 'import' : 'all');
            $joins = ['LEFT JOIN email_suppressions s ON s.email_address=LOWER(TRIM(l.email_address))'];
            $where = ["l.status='active'", 'l.archived_at IS NULL', 's.id IS NULL'];
            $params = [];
            if ($audience === 'import') {
                if ($leadImportId < 1) throw new InvalidArgumentException('Choose a CSV import before queueing this campaign.');
                $joins[] = 'INNER JOIN email_lead_import_members m ON m.lead_id=l.id';
                $where[] = 'm.import_id=:import';
                $params[':import'] = $leadImportId;
            }
            if ($audience === 'filtered') {
                if (!empty($filters['lead_status'])) { $where[] = 'l.lead_status=:lead_status'; $params[':lead_status'] = mb_substr((string) $filters['lead_status'], 0, 40); }
                if (!empty($filters['lead_source'])) { $where[] = 'l.lead_source=:lead_source'; $params[':lead_source'] = mb_substr((string) $filters['lead_source'], 0, 80); }
                $state = (string) ($filters['opportunity_state'] ?? '');
                if ($state === 'open') $where[] = "EXISTS(SELECT 1 FROM opportunities o WHERE o.lead_id=l.id AND o.archived_at IS NULL AND o.stage NOT IN ('Won','Lost'))";
                if ($state === 'none') $where[] = 'NOT EXISTS(SELECT 1 FROM opportunities o WHERE o.lead_id=l.id AND o.archived_at IS NULL)';
                if ($state === 'lost') $where[] = "EXISTS(SELECT 1 FROM opportunities o WHERE o.lead_id=l.id AND o.stage='Lost')";
                if ($state === 'customer') $where[] = "l.lead_status='Converted'";
            }
            $leads = $this->pdo->prepare('SELECT DISTINCT l.* FROM email_leads l ' . implode(' ', $joins) . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY l.id');
            $leads->execute($params);
            $leads = $leads->fetchAll(PDO::FETCH_ASSOC);
            $insert = $this->pdo->prepare("INSERT IGNORE INTO email_sales_recipients
                (campaign_id,lead_id,email_address,first_name,last_name,company,unsubscribe_token_hash)
                VALUES(:campaign,:lead,:email,:first,:last,:company,:token_hash)");
            $updateToken = $this->pdo->prepare('UPDATE email_sales_recipients SET unsubscribe_token_hash=:hash WHERE id=:id');
            $count = 0;
            foreach ($leads as $lead) {
                if (!filter_var($lead['email_address'], FILTER_VALIDATE_EMAIL)) {
                    continue;
                }
                $insert->execute([':campaign' => $campaignId, ':lead' => $lead['id'], ':email' => $lead['email_address'], ':first' => $lead['first_name'], ':last' => $lead['last_name'], ':company' => $lead['company'], ':token_hash' => hash('sha256', random_bytes(32))]);
                if ($insert->rowCount() === 1) {
                    $recipientId = (int) $this->pdo->lastInsertId();
                    $token = EmailToken::make('sales', $recipientId, $lead['email_address']);
                    $updateToken->execute([':hash' => hash('sha256', $token), ':id' => $recipientId]);
                    $count++;
                }
            }
            if ($count === 0 && $campaign['status'] === 'draft') {
                throw new RuntimeException('No eligible leads matched this campaign audience.');
            }
            $this->pdo->prepare("UPDATE email_sales_campaigns SET status='queued',queued_at=COALESCE(queued_at,NOW()),completed_at=NULL WHERE id=:id")
                ->execute([':id' => $campaignId]);
            if ($ownsTransaction) $this->pdo->commit();
            return $count;
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function process(): array
    {
        $newsletterCount = $this->processNewsletterBatch();
        $salesCount = $this->processSalesMessage();
        return ['newsletter_processed' => $newsletterCount, 'sales_processed' => $salesCount];
    }

    private function processNewsletterBatch(): int
    {
        $interval = max(1, (int) normanEnv('NORMAN_NEWSLETTER_INTERVAL_MINUTES', '5'));
        $batchSize = max(1, min(250, (int) normanEnv('NORMAN_NEWSLETTER_BATCH_SIZE', '25')));
        $stmt = $this->pdo->prepare("SELECT id FROM email_newsletters
            WHERE status IN ('queued','sending') AND (last_batch_at IS NULL OR last_batch_at <= DATE_SUB(NOW(), INTERVAL :minutes MINUTE))
            ORDER BY queued_at,id LIMIT 1");
        $stmt->bindValue(':minutes', $interval, PDO::PARAM_INT);
        $stmt->execute();
        $newsletterId = (int) $stmt->fetchColumn();
        if (!$newsletterId) {
            return 0;
        }
        $claim = $this->pdo->prepare("UPDATE email_newsletters SET status='sending',started_at=COALESCE(started_at,NOW()),last_batch_at=NOW()
            WHERE id=:id AND (last_batch_at IS NULL OR last_batch_at <= DATE_SUB(NOW(), INTERVAL :minutes MINUTE))");
        $claim->bindValue(':id', $newsletterId, PDO::PARAM_INT);
        $claim->bindValue(':minutes', $interval, PDO::PARAM_INT);
        $claim->execute();
        if ($claim->rowCount() !== 1) {
            return 0;
        }

        $newsletter = $this->pdo->prepare('SELECT * FROM email_newsletters WHERE id=:id');
        $newsletter->execute([':id' => $newsletterId]);
        $record = $newsletter->fetch(PDO::FETCH_ASSOC);
        $recipients = $this->pdo->prepare("SELECT * FROM email_newsletter_recipients
            WHERE newsletter_id=:id AND status IN ('pending','failed') AND (next_attempt_at IS NULL OR next_attempt_at<=NOW()) AND attempt_count<3
            ORDER BY id LIMIT {$batchSize}");
        $recipients->execute([':id' => $newsletterId]);
        $processed = 0;
        foreach ($recipients->fetchAll(PDO::FETCH_ASSOC) as $recipient) {
            $this->sendNewsletter($record, $recipient);
            $processed++;
        }
        $this->finishIfExhausted('email_newsletters', 'email_newsletter_recipients', $newsletterId, 'newsletter_id');
        return $processed;
    }

    private function processSalesMessage(): int
    {
        $interval = max(1, (int) normanEnv('NORMAN_SALES_INTERVAL_MINUTES', '3'));
        $stmt = $this->pdo->prepare("SELECT id FROM email_sales_campaigns
            WHERE status IN ('queued','sending') AND (last_sent_at IS NULL OR last_sent_at <= DATE_SUB(NOW(), INTERVAL :minutes MINUTE))
            ORDER BY queued_at,id LIMIT 1");
        $stmt->bindValue(':minutes', $interval, PDO::PARAM_INT);
        $stmt->execute();
        $campaignId = (int) $stmt->fetchColumn();
        if (!$campaignId) {
            return 0;
        }
        $claim = $this->pdo->prepare("UPDATE email_sales_campaigns SET status='sending',started_at=COALESCE(started_at,NOW()),last_sent_at=NOW()
            WHERE id=:id AND (last_sent_at IS NULL OR last_sent_at <= DATE_SUB(NOW(), INTERVAL :minutes MINUTE))");
        $claim->bindValue(':id', $campaignId, PDO::PARAM_INT);
        $claim->bindValue(':minutes', $interval, PDO::PARAM_INT);
        $claim->execute();
        if ($claim->rowCount() !== 1) {
            return 0;
        }
        $campaign = $this->pdo->prepare("SELECT c.*,t.subject_template,t.body_template,s.html_body signature_html
            FROM email_sales_campaigns c INNER JOIN email_sales_templates t ON t.id=c.template_id
            LEFT JOIN email_signatures s ON s.id=t.signature_id WHERE c.id=:id");
        $campaign->execute([':id' => $campaignId]);
        $record = $campaign->fetch(PDO::FETCH_ASSOC);
        $recipient = $this->pdo->prepare("SELECT * FROM email_sales_recipients
            WHERE campaign_id=:id AND status IN ('pending','failed') AND (next_attempt_at IS NULL OR next_attempt_at<=NOW()) AND attempt_count<3
            ORDER BY id LIMIT 1");
        $recipient->execute([':id' => $campaignId]);
        $lead = $recipient->fetch(PDO::FETCH_ASSOC);
        if ($lead) {
            $this->sendSales($record, $lead);
        }
        $this->finishIfExhausted('email_sales_campaigns', 'email_sales_recipients', $campaignId, 'campaign_id');
        return $lead ? 1 : 0;
    }

    private function sendNewsletter(array $newsletter, array $recipient): void
    {
        if (!$this->mailer) {
            throw new LogicException('Email transport is not configured for queue processing.');
        }
        if ($this->isSuppressed($recipient['email_address'])) {
            $this->markRecipient('email_newsletter_recipients', $recipient, 'skipped', 'Recipient is suppressed.', 'newsletter', (int) $newsletter['id']);
            return;
        }
        $token = EmailToken::make('newsletter', (int) $recipient['id'], $recipient['email_address']);
        $url = $this->unsubscribeUrl($token);
        $unsubscribePlaceholders = ['{UnsubscribeURL}', '%7BUnsubscribeURL%7D'];
        $hasPlacedUnsubscribeLink = str_contains($newsletter['html_body'], $unsubscribePlaceholders[0])
            || str_contains($newsletter['html_body'], $unsubscribePlaceholders[1]);
        $body = $this->replaceVariables($newsletter['html_body'], $recipient);
        $body = str_replace($unsubscribePlaceholders, htmlspecialchars($url, ENT_QUOTES, 'UTF-8'), $body);
        if (!$hasPlacedUnsubscribeLink) {
            $body .= $this->unsubscribeFooter($url);
        }
        $profile = $this->config['newsletter'] ?? [];
        try {
            $this->mailer->send([
                'smtp_config' => $profile,
                'from_email' => $profile['from_email'] ?? normanEnv('NORMAN_NEWSLETTER_FROM_EMAIL', 'newsletters@normanandcompany.com'),
                'from_name' => $profile['from_name'] ?? normanEnv('NORMAN_NEWSLETTER_FROM_NAME', 'Norman and Company Newsletter'),
                'reply_to' => $profile['reply_to'] ?? normanEnv('NORMAN_NEWSLETTER_REPLY_TO', 'newsletters@normanandcompany.com'),
                'to_email' => $recipient['email_address'],
                'subject' => $this->replaceVariables($newsletter['subject'], $recipient),
                'html_body' => $this->wrapHtml($body),
                'unsubscribe_url' => $url
            ]);
            $this->markRecipient('email_newsletter_recipients', $recipient, 'sent', null, 'newsletter', (int) $newsletter['id']);
        } catch (Throwable $e) {
            $this->markRecipient('email_newsletter_recipients', $recipient, 'failed', $e->getMessage(), 'newsletter', (int) $newsletter['id']);
        }
    }

    private function sendSales(array $campaign, array $recipient): void
    {
        if (!$this->mailer) {
            throw new LogicException('Email transport is not configured for queue processing.');
        }
        if ($this->isSuppressed($recipient['email_address'])) {
            $this->markRecipient('email_sales_recipients', $recipient, 'skipped', 'Recipient is suppressed.', 'sales', (int) $campaign['id']);
            return;
        }
        $token = EmailToken::make('sales', (int) $recipient['id'], $recipient['email_address']);
        $url = $this->unsubscribeUrl($token);
        $subject = $this->replaceVariables($campaign['subject_template'], $recipient);
        $bodyText = $this->replaceVariables($campaign['body_template'], $recipient);
        $body = nl2br(htmlspecialchars($bodyText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        if (!empty($campaign['signature_html'])) {
            $body .= '<div style="margin-top:24px">' . $campaign['signature_html'] . '</div>';
        }
        $body .= $this->unsubscribeFooter($url);
        $profile = $this->config['sales'] ?? [];
        try {
            $this->mailer->send([
                'smtp_config' => $profile,
                'from_email' => $profile['from_email'] ?? normanRequiredEnv('NORMAN_SALES_FROM_EMAIL'),
                'from_name' => $profile['from_name'] ?? normanEnv('NORMAN_SALES_FROM_NAME', 'Norman and Company'),
                'reply_to' => $profile['reply_to'] ?? ($profile['from_email'] ?? normanRequiredEnv('NORMAN_SALES_FROM_EMAIL')),
                'to_email' => $recipient['email_address'],
                'subject' => $subject,
                'html_body' => $this->wrapHtml($body),
                'unsubscribe_url' => $url
            ]);
            $this->markRecipient('email_sales_recipients', $recipient, 'sent', null, 'sales', (int) $campaign['id']);
            $this->pdo->prepare('UPDATE email_sales_recipients SET subject_rendered=:subject WHERE id=:id')->execute([':subject' => $subject, ':id' => $recipient['id']]);
            $this->pdo->prepare('UPDATE email_leads SET last_campaign_id=:campaign,last_template_id=:template,last_contacted_at=NOW(),total_emails_sent=total_emails_sent+1 WHERE id=:id')
                ->execute([':campaign' => $campaign['id'], ':template' => $campaign['template_id'], ':id' => $recipient['lead_id']]);
            $this->pdo->prepare("INSERT INTO sales_activities(lead_id,campaign_id,activity_type,title,description) VALUES(:lead,:campaign,'email_sent','Sales campaign email sent',:subject)")
                ->execute([':lead' => $recipient['lead_id'], ':campaign' => $campaign['id'], ':subject' => $subject]);
        } catch (Throwable $e) {
            $this->markRecipient('email_sales_recipients', $recipient, 'failed', $e->getMessage(), 'sales', (int) $campaign['id']);
        }
    }

    private function markRecipient(string $table, array $recipient, string $status, ?string $error, string $type, int $messageId): void
    {
        $attempts = (int) $recipient['attempt_count'] + 1;
        $terminal = $status !== 'failed' || $attempts >= 3;
        $sql = "UPDATE {$table} SET status=:status,attempt_count=:attempts,last_error=:error,next_attempt_at="
            . ($terminal ? 'NULL' : 'DATE_ADD(NOW(), INTERVAL 15 MINUTE)')
            . ($status === 'sent' ? ',sent_at=NOW()' : '') . ' WHERE id=:id';
        $this->pdo->prepare($sql)->execute([':status' => $status, ':attempts' => $attempts, ':error' => $error ? substr($error, 0, 1000) : null, ':id' => $recipient['id']]);
        $this->pdo->prepare('INSERT INTO email_delivery_log(message_type,message_id,recipient_id,email_address,status,error_message) VALUES(:type,:message,:recipient,:email,:status,:error)')
            ->execute([':type' => $type, ':message' => $messageId, ':recipient' => $recipient['id'], ':email' => $recipient['email_address'], ':status' => $status === 'failed' ? 'failed' : ($status === 'sent' ? 'sent' : 'skipped'), ':error' => $error ? substr($error, 0, 1000) : null]);
    }

    private function finishIfExhausted(string $parentTable, string $recipientTable, int $id, string $foreignKey): void
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$recipientTable} WHERE {$foreignKey}=:id AND (status='pending' OR status='processing' OR (status='failed' AND attempt_count<3))");
        $stmt->execute([':id' => $id]);
        if ((int) $stmt->fetchColumn() === 0) {
            $this->pdo->prepare("UPDATE {$parentTable} SET status='completed',completed_at=NOW() WHERE id=:id")->execute([':id' => $id]);
        }
    }

    private function lockedRecord(string $table, int $id): array|false
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$table} WHERE id=:id FOR UPDATE");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function isSuppressed(string $email): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM email_suppressions WHERE email_address=:email');
        $stmt->execute([':email' => strtolower(trim($email))]);
        return (bool) $stmt->fetchColumn();
    }

    private function replaceVariables(string $template, array $recipient): string
    {
        return strtr($template, [
            '{FirstName}' => (string) ($recipient['first_name'] ?? ''),
            '{LastName}' => (string) ($recipient['last_name'] ?? ''),
            '{Company}' => (string) ($recipient['company'] ?? ''),
            '{EmailAddress}' => (string) ($recipient['email_address'] ?? '')
        ]);
    }

    private function unsubscribeUrl(string $token): string
    {
        return rtrim((string) normanEnv('NORMAN_SITE_URL', 'https://www.normanandcompany.com'), '/') . '/unsubscribe.php?token=' . rawurlencode($token);
    }

    private function unsubscribeFooter(string $url): string
    {
        return '<p style="margin-top:32px;padding-top:16px;border-top:1px solid #dddddd;color:#666;font-size:12px">You are receiving this email from Norman and Company. <a href="'
            . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">Unsubscribe</a>.</p>';
    }

    private function wrapHtml(string $body): string
    {
        return '<!doctype html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;color:#1b2f4f;line-height:1.55">' . $body . '</body></html>';
    }
}
