<?php

declare(strict_types=1);

final class CrmService
{
    public const LEAD_STATUSES = ['New', 'Contacted', 'Nurturing', 'Qualified', 'Disqualified', 'Converted', 'Archived'];
    public const LEAD_SOURCES = ['Website', 'Manual Entry', 'Email Campaign', 'Zoo Outreach', 'Aquarium Outreach', 'Museum Outreach', 'Retailer Outreach', 'Event Network / Retail Operator', 'Referral', 'Existing Customer', 'Other'];
    public const PRIORITIES = ['Low', 'Normal', 'High', 'Urgent'];
    public const OPPORTUNITY_STAGES = ['New', 'Discovery', 'Needs Analysis', 'Proposal', 'Negotiation', 'Verbal Commitment', 'Won', 'Lost'];
    public const ORDER_STATUSES = ['Draft', 'Ready', 'Sent', 'Accepted', 'Awaiting Payment', 'Paid', 'Processing', 'Fulfilled', 'Cancelled', 'Refunded'];

    private const ORDER_TRANSITIONS = [
        'Draft' => ['Ready', 'Cancelled'],
        'Ready' => ['Draft', 'Sent', 'Accepted', 'Awaiting Payment', 'Cancelled'],
        'Sent' => ['Accepted', 'Awaiting Payment', 'Cancelled'],
        'Accepted' => ['Awaiting Payment', 'Paid', 'Cancelled'],
        'Awaiting Payment' => ['Paid', 'Cancelled'],
        'Paid' => ['Processing', 'Fulfilled', 'Refunded'],
        'Processing' => ['Fulfilled', 'Refunded'],
        'Fulfilled' => ['Refunded'],
        'Cancelled' => [],
        'Refunded' => []
    ];

    public function __construct(private PDO $pdo, private int $userId)
    {
    }

    public static function clean(?string $value, int $max = 255): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    public static function money(mixed $value, string $label = 'Amount'): string
    {
        $value = trim((string) $value);
        if ($value === '' || !preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $value)) {
            throw new InvalidArgumentException("{$label} must be a non-negative amount with no more than two decimals.");
        }
        return number_format((float) $value, 2, '.', '');
    }

    public function logActivity(int $leadId, string $type, string $title, ?string $description = null, ?int $opportunityId = null, ?int $orderId = null, ?int $campaignId = null, ?array $metadata = null): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO sales_activities(lead_id,opportunity_id,sales_order_id,campaign_id,user_id,activity_type,title,description,metadata_json) VALUES(:lead,:opportunity,:sales_order,:campaign,:user,:type,:title,:description,:metadata)');
        $stmt->execute([
            ':lead' => $leadId, ':opportunity' => $opportunityId, ':sales_order' => $orderId,
            ':campaign' => $campaignId, ':user' => $this->userId, ':type' => $type,
            ':title' => $title, ':description' => $description,
            ':metadata' => $metadata ? json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) : null
        ]);
    }

    public function audit(string $action, string $entityType, int $entityId, ?array $before, ?array $after): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO admin_audit_log(user_id,action,entity_type,entity_id,before_json,after_json) VALUES(:user,:action,:type,:id,:before,:after)');
        $stmt->execute([
            ':user' => $this->userId, ':action' => $action, ':type' => $entityType, ':id' => $entityId,
            ':before' => $before ? json_encode($before, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) : null,
            ':after' => $after ? json_encode($after, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) : null
        ]);
    }

    public function qualifyLead(int $leadId, ?string $notes): void
    {
        $ownsTransaction = $this->beginUnitOfWork();
        try {
            $lead = $this->leadForUpdate($leadId);
            if (!filter_var($lead['email_address'], FILTER_VALIDATE_EMAIL) || trim((string) $lead['first_name']) === '') {
                throw new InvalidArgumentException('A valid email address and first name are required before qualification.');
            }
            $stmt = $this->pdo->prepare("UPDATE email_leads SET lead_status='Qualified',qualified_at=NOW(),qualified_by_user_id=:user,qualification_notes=COALESCE(:notes,qualification_notes),disqualification_reason=NULL WHERE id=:id");
            $stmt->execute([':user' => $this->userId, ':notes' => self::clean($notes, 10000), ':id' => $leadId]);
            $this->logActivity($leadId, 'lead_qualified', 'Lead qualified', self::clean($notes, 10000));
            $this->audit('lead_qualified', 'lead', $leadId, $lead, ['lead_status' => 'Qualified']);
            $this->commitUnitOfWork($ownsTransaction);
        } catch (Throwable $e) {
            $this->rollbackUnitOfWork($ownsTransaction);
            throw $e;
        }
    }

    public function disqualifyLead(int $leadId, string $reason): void
    {
        $reason = self::clean($reason, 255) ?? '';
        if ($reason === '') throw new InvalidArgumentException('A disqualification reason is required.');
        $ownsTransaction = $this->beginUnitOfWork();
        try {
            $lead = $this->leadForUpdate($leadId);
            $this->pdo->prepare("UPDATE email_leads SET lead_status='Disqualified',disqualification_reason=:reason WHERE id=:id")
                ->execute([':reason' => $reason, ':id' => $leadId]);
            $this->logActivity($leadId, 'lead_disqualified', 'Lead disqualified', $reason);
            $this->audit('lead_disqualified', 'lead', $leadId, $lead, ['lead_status' => 'Disqualified', 'reason' => $reason]);
            $this->commitUnitOfWork($ownsTransaction);
        } catch (Throwable $e) {
            $this->rollbackUnitOfWork($ownsTransaction);
            throw $e;
        }
    }

    public function createOpportunity(int $leadId, array $data): int
    {
        $ownsTransaction = $this->beginUnitOfWork();
        try {
            $lead = $this->leadForUpdate($leadId);
            if ($lead['lead_status'] !== 'Qualified') throw new InvalidArgumentException('Only qualified leads can create opportunities.');
            $name = self::clean($data['opportunity_name'] ?? null, 180);
            if (!$name) throw new InvalidArgumentException('Opportunity name is required.');
            $estimated = self::money($data['estimated_value'] ?? '0', 'Estimated value');
            $probability = max(0, min(100, (int) ($data['probability'] ?? 10)));
            $stmt = $this->pdo->prepare("INSERT INTO opportunities(lead_id,opportunity_name,assigned_user_id,stage,probability,estimated_value,expected_close_date,source,originating_campaign_id,description,notes) VALUES(:lead,:name,:owner,'New',:probability,:value,:close,:source,:campaign,:description,:notes)");
            $stmt->execute([
                ':lead' => $leadId, ':name' => $name, ':owner' => (int) ($data['assigned_user_id'] ?? 0) ?: ($lead['assigned_user_id'] ?: null),
                ':probability' => $probability, ':value' => $estimated, ':close' => self::clean($data['expected_close_date'] ?? null, 10),
                ':source' => $lead['lead_source'], ':campaign' => $lead['last_campaign_id'] ?: null,
                ':description' => self::clean($data['description'] ?? null, 20000), ':notes' => self::clean($data['notes'] ?? null, 20000)
            ]);
            $id = (int) $this->pdo->lastInsertId();
            $this->logActivity($leadId, 'opportunity_created', 'Opportunity created', $name, $id, null, $lead['last_campaign_id'] ? (int) $lead['last_campaign_id'] : null);
            $this->audit('opportunity_created', 'opportunity', $id, null, ['lead_id' => $leadId, 'name' => $name]);
            $this->commitUnitOfWork($ownsTransaction);
            return $id;
        } catch (Throwable $e) {
            $this->rollbackUnitOfWork($ownsTransaction);
            throw $e;
        }
    }

    public function setOpportunityStage(int $id, string $stage, ?string $reason = null): void
    {
        if (!in_array($stage, self::OPPORTUNITY_STAGES, true)) throw new InvalidArgumentException('Choose a valid opportunity stage.');
        if ($stage === 'Lost' && !self::clean($reason, 255)) throw new InvalidArgumentException('A loss reason is required.');
        $ownsTransaction = $this->beginUnitOfWork();
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM opportunities WHERE id=:id FOR UPDATE');
            $stmt->execute([':id' => $id]);
            $opportunity = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$opportunity) throw new InvalidArgumentException('Opportunity not found.');
            $sql = "UPDATE opportunities SET stage=:stage,lost_reason=:reason,lost_at=" . ($stage === 'Lost' ? 'NOW()' : 'NULL') . ",won_at=" . ($stage === 'Won' ? 'NOW()' : 'NULL') . ' WHERE id=:id';
            $this->pdo->prepare($sql)->execute([':stage' => $stage, ':reason' => $stage === 'Lost' ? self::clean($reason, 255) : null, ':id' => $id]);
            $this->logActivity((int) $opportunity['lead_id'], $stage === 'Won' ? 'opportunity_won' : ($stage === 'Lost' ? 'opportunity_lost' : 'opportunity_stage_changed'), "Opportunity moved to {$stage}", self::clean($reason, 255), $id);
            $this->audit('opportunity_stage_changed', 'opportunity', $id, ['stage' => $opportunity['stage']], ['stage' => $stage, 'reason' => self::clean($reason, 255)]);
            $this->commitUnitOfWork($ownsTransaction);
        } catch (Throwable $e) {
            $this->rollbackUnitOfWork($ownsTransaction);
            throw $e;
        }
    }

    public function updateOpportunity(int $id, array $data): void
    {
        $name = self::clean($data['opportunity_name'] ?? null, 180);
        if (!$name) throw new InvalidArgumentException('Opportunity name is required.');
        $probability = max(0, min(100, (int) ($data['probability'] ?? 0)));
        $ownsTransaction = $this->beginUnitOfWork();
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM opportunities WHERE id=:id AND archived_at IS NULL FOR UPDATE');
            $stmt->execute([':id' => $id]);
            $before = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$before) throw new InvalidArgumentException('Opportunity not found.');
            $this->pdo->prepare('UPDATE opportunities SET opportunity_name=:name,assigned_user_id=:owner,probability=:probability,expected_close_date=:close_date,description=:description,notes=:notes WHERE id=:id')->execute([
                ':name' => $name, ':owner' => (int) ($data['assigned_user_id'] ?? 0) ?: null, ':probability' => $probability,
                ':close_date' => self::clean($data['expected_close_date'] ?? null, 10), ':description' => self::clean($data['description'] ?? null, 20000),
                ':notes' => self::clean($data['notes'] ?? null, 20000), ':id' => $id
            ]);
            $this->logActivity((int) $before['lead_id'], 'opportunity_updated', 'Opportunity updated', null, $id);
            $this->audit('opportunity_updated', 'opportunity', $id, $before, ['opportunity_name' => $name, 'probability' => $probability]);
            $this->commitUnitOfWork($ownsTransaction);
        } catch (Throwable $e) {
            $this->rollbackUnitOfWork($ownsTransaction);
            throw $e;
        }
    }

    public function saveOpportunityItem(int $opportunityId, int $productId, int $quantity, mixed $unitPrice, mixed $discount): void
    {
        if ($quantity < 1 || $quantity > 100000) throw new InvalidArgumentException('Quantity must be between 1 and 100,000.');
        $price = self::money($unitPrice, 'Unit price');
        $discount = self::money($discount ?: '0', 'Discount');
        $lineTotal = number_format(max(0, ((float) $price * $quantity) - (float) $discount), 2, '.', '');
        $check = $this->pdo->prepare('SELECT o.lead_id,p.id FROM opportunities o CROSS JOIN products p WHERE o.id=:opportunity AND p.id=:product');
        $check->execute([':opportunity' => $opportunityId, ':product' => $productId]);
        $row = $check->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new InvalidArgumentException('Choose a valid opportunity and product.');
        $stmt = $this->pdo->prepare('INSERT INTO opportunity_items(opportunity_id,product_id,quantity,proposed_unit_price,discount_amount,line_total) VALUES(:opportunity,:product,:quantity,:price,:discount,:total) ON DUPLICATE KEY UPDATE quantity=VALUES(quantity),proposed_unit_price=VALUES(proposed_unit_price),discount_amount=VALUES(discount_amount),line_total=VALUES(line_total)');
        $stmt->execute([':opportunity' => $opportunityId, ':product' => $productId, ':quantity' => $quantity, ':price' => $price, ':discount' => $discount, ':total' => $lineTotal]);
        $this->pdo->prepare('UPDATE opportunities SET estimated_value=(SELECT COALESCE(SUM(line_total),0) FROM opportunity_items WHERE opportunity_id=:id) WHERE id=:id')->execute([':id' => $opportunityId]);
        $this->logActivity((int) $row['lead_id'], 'opportunity_product_updated', 'Opportunity products updated', null, $opportunityId);
    }

    public function createSalesOrder(int $opportunityId, array $data): int
    {
        $ownsTransaction = $this->beginUnitOfWork();
        try {
            $stmt = $this->pdo->prepare('SELECT o.*,l.first_name,l.last_name,l.company,l.email_address,l.phone,l.lead_status FROM opportunities o INNER JOIN email_leads l ON l.id=o.lead_id WHERE o.id=:id FOR UPDATE');
            $stmt->execute([':id' => $opportunityId]);
            $opportunity = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$opportunity || in_array($opportunity['stage'], ['Lost'], true)) throw new InvalidArgumentException('This opportunity cannot create a sales order.');
            $items = $this->pdo->prepare('SELECT i.*,p.product_name,p.sku,p.isbn,p.product_description FROM opportunity_items i INNER JOIN products p ON p.id=i.product_id WHERE i.opportunity_id=:id ORDER BY i.id');
            $items->execute([':id' => $opportunityId]);
            $items = $items->fetchAll(PDO::FETCH_ASSOC);
            if (!$items) throw new InvalidArgumentException('Add at least one product before creating a sales order.');
            $subtotal = array_reduce($items, fn(float $sum, array $item): float => $sum + ((float) $item['proposed_unit_price'] * (int) $item['quantity']), 0.0);
            $discount = array_reduce($items, fn(float $sum, array $item): float => $sum + (float) $item['discount_amount'], 0.0);
            $tax = (float) self::money($data['tax_total'] ?? '0', 'Tax');
            $shipping = (float) self::money($data['shipping_total'] ?? '0', 'Shipping');
            $grand = max(0, $subtotal - $discount + $tax + $shipping);
            $contact = trim($opportunity['first_name'] . ' ' . ($opportunity['last_name'] ?? ''));
            $create = $this->pdo->prepare("INSERT INTO sales_orders(lead_id,opportunity_id,assigned_user_id,originating_campaign_id,status,subtotal,discount_total,tax_total,shipping_total,grand_total,company_snapshot,contact_name_snapshot,email_snapshot,phone_snapshot,billing_address_snapshot,shipping_address_snapshot,customer_notes,internal_notes) VALUES(:lead,:opportunity,:owner,:campaign,'Draft',:subtotal,:discount,:tax,:shipping,:grand,:company,:contact,:email,:phone,:billing,:shipping_address,:customer_notes,:internal_notes)");
            $create->execute([
                ':lead' => $opportunity['lead_id'], ':opportunity' => $opportunityId, ':owner' => $opportunity['assigned_user_id'], ':campaign' => $opportunity['originating_campaign_id'],
                ':subtotal' => number_format($subtotal, 2, '.', ''), ':discount' => number_format($discount, 2, '.', ''), ':tax' => number_format($tax, 2, '.', ''), ':shipping' => number_format($shipping, 2, '.', ''), ':grand' => number_format($grand, 2, '.', ''),
                ':company' => $opportunity['company'], ':contact' => $contact, ':email' => $opportunity['email_address'], ':phone' => $opportunity['phone'],
                ':billing' => self::clean($data['billing_address_snapshot'] ?? null, 5000), ':shipping_address' => self::clean($data['shipping_address_snapshot'] ?? null, 5000),
                ':customer_notes' => self::clean($data['customer_notes'] ?? null, 10000), ':internal_notes' => self::clean($data['internal_notes'] ?? null, 10000)
            ]);
            $orderId = (int) $this->pdo->lastInsertId();
            $orderNumber = sprintf('SO-%s-%06d', date('Y'), $orderId);
            $this->pdo->prepare('UPDATE sales_orders SET order_number=:number WHERE id=:id')->execute([':number' => $orderNumber, ':id' => $orderId]);
            $insert = $this->pdo->prepare('INSERT INTO sales_order_items(sales_order_id,product_id,product_name_snapshot,sku_snapshot,isbn_snapshot,product_description_snapshot,quantity,unit_price,discount_amount,line_total) VALUES(:order,:product,:name,:sku,:isbn,:description,:quantity,:price,:discount,:total)');
            foreach ($items as $item) {
                $insert->execute([':order' => $orderId, ':product' => $item['product_id'], ':name' => $item['product_name'], ':sku' => $item['sku'], ':isbn' => $item['isbn'], ':description' => $item['product_description'], ':quantity' => $item['quantity'], ':price' => $item['proposed_unit_price'], ':discount' => $item['discount_amount'], ':total' => $item['line_total']]);
            }
            $this->logActivity((int) $opportunity['lead_id'], 'sales_order_created', 'Sales order created', $orderNumber, $opportunityId, $orderId, $opportunity['originating_campaign_id'] ? (int) $opportunity['originating_campaign_id'] : null);
            $this->audit('sales_order_created', 'sales_order', $orderId, null, ['order_number' => $orderNumber, 'grand_total' => $grand]);
            $this->commitUnitOfWork($ownsTransaction);
            return $orderId;
        } catch (Throwable $e) {
            $this->rollbackUnitOfWork($ownsTransaction);
            throw $e;
        }
    }

    public function changeOrderStatus(int $orderId, string $status): void
    {
        if (!in_array($status, self::ORDER_STATUSES, true)) throw new InvalidArgumentException('Choose a valid sales order status.');
        $ownsTransaction = $this->beginUnitOfWork();
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM sales_orders WHERE id=:id FOR UPDATE');
            $stmt->execute([':id' => $orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) throw new InvalidArgumentException('Sales order not found.');
            if (!in_array($status, self::ORDER_TRANSITIONS[$order['status']] ?? [], true)) throw new InvalidArgumentException("A {$order['status']} order cannot move directly to {$status}.");
            $dateColumn = ['Sent' => 'sent_at', 'Accepted' => 'accepted_at', 'Fulfilled' => 'fulfilled_at', 'Cancelled' => 'cancelled_at'][$status] ?? null;
            $sql = 'UPDATE sales_orders SET status=:status' . ($dateColumn ? ",{$dateColumn}=NOW()" : '') . ' WHERE id=:id';
            $this->pdo->prepare($sql)->execute([':status' => $status, ':id' => $orderId]);
            $this->logActivity((int) $order['lead_id'], 'sales_order_status_changed', "Sales order moved to {$status}", $order['order_number'], $order['opportunity_id'] ? (int) $order['opportunity_id'] : null, $orderId);
            $this->audit('sales_order_status_changed', 'sales_order', $orderId, ['status' => $order['status']], ['status' => $status]);
            $this->commitUnitOfWork($ownsTransaction);
        } catch (Throwable $e) {
            $this->rollbackUnitOfWork($ownsTransaction);
            throw $e;
        }
    }

    public function updateDraftOrder(int $orderId, array $data): void
    {
        $tax = (float) self::money($data['tax_total'] ?? '0', 'Tax');
        $shipping = (float) self::money($data['shipping_total'] ?? '0', 'Shipping');
        $ownsTransaction = $this->beginUnitOfWork();
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM sales_orders WHERE id=:id FOR UPDATE');
            $stmt->execute([':id' => $orderId]);
            $before = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$before) throw new InvalidArgumentException('Sales order not found.');
            if ($before['status'] !== 'Draft') throw new InvalidArgumentException('Only Draft sales orders can be edited.');
            $totals = $this->pdo->prepare('SELECT COALESCE(SUM(unit_price*quantity),0) subtotal,COALESCE(SUM(discount_amount),0) discount_total FROM sales_order_items WHERE sales_order_id=:id');
            $totals->execute([':id' => $orderId]);
            $totals = $totals->fetch(PDO::FETCH_ASSOC);
            $grand = max(0, (float) $totals['subtotal'] - (float) $totals['discount_total'] + $tax + $shipping);
            $this->pdo->prepare('UPDATE sales_orders SET tax_total=:tax,shipping_total=:shipping,subtotal=:subtotal,discount_total=:discount,grand_total=:grand,billing_address_snapshot=:billing,shipping_address_snapshot=:shipping_address,customer_notes=:customer_notes,internal_notes=:internal_notes WHERE id=:id')->execute([
                ':tax' => number_format($tax, 2, '.', ''), ':shipping' => number_format($shipping, 2, '.', ''), ':subtotal' => $totals['subtotal'], ':discount' => $totals['discount_total'], ':grand' => number_format($grand, 2, '.', ''),
                ':billing' => self::clean($data['billing_address_snapshot'] ?? null, 5000), ':shipping_address' => self::clean($data['shipping_address_snapshot'] ?? null, 5000),
                ':customer_notes' => self::clean($data['customer_notes'] ?? null, 10000), ':internal_notes' => self::clean($data['internal_notes'] ?? null, 10000), ':id' => $orderId
            ]);
            $this->logActivity((int) $before['lead_id'], 'sales_order_updated', 'Draft sales order updated', $before['order_number'], $before['opportunity_id'] ? (int) $before['opportunity_id'] : null, $orderId);
            $this->audit('sales_order_updated', 'sales_order', $orderId, $before, ['grand_total' => number_format($grand, 2, '.', '')]);
            $this->commitUnitOfWork($ownsTransaction);
        } catch (Throwable $e) {
            $this->rollbackUnitOfWork($ownsTransaction);
            throw $e;
        }
    }

    public function recordPayment(int $orderId, string $reference, ?string $provider = null): int
    {
        $reference = self::clean($reference, 255) ?? '';
        if ($reference === '') throw new InvalidArgumentException('A unique payment reference is required.');
        $provider = self::clean($provider, 100) ?? 'manual';
        $ownsTransaction = $this->beginUnitOfWork();
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM sales_orders WHERE id=:id FOR UPDATE');
            $stmt->execute([':id' => $orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) throw new InvalidArgumentException('Sales order not found.');
            $existing = $this->pdo->prepare('SELECT id FROM transactions WHERE sales_order_id=:order OR (payment_provider=:provider AND transaction_reference=:reference) LIMIT 1');
            $existing->execute([':order' => $orderId, ':provider' => $provider, ':reference' => $reference]);
            $existingId = (int) $existing->fetchColumn();
            if ($existingId) {
                $this->commitUnitOfWork($ownsTransaction);
                return $existingId;
            }
            if (in_array($order['status'], ['Cancelled', 'Refunded'], true)) throw new InvalidArgumentException('Cancelled or refunded orders cannot be paid.');
            $insert = $this->pdo->prepare("INSERT INTO transactions(order_id,sales_order_id,opportunity_id,lead_id,originating_campaign_id,transaction_reference,payment_provider,transaction_type,currency_code,transaction_amount,product_sales_amount,shipping_amount,sales_tax_amount,discount_amount,transaction_status,processed_at,visible) VALUES(NULL,:order,:opportunity,:lead,:campaign,:reference,:provider,'sale',:currency,:amount,:subtotal,:shipping,:tax,:discount,'paid',NOW(),1)");
            $insert->execute([':order' => $orderId, ':opportunity' => $order['opportunity_id'], ':lead' => $order['lead_id'], ':campaign' => $order['originating_campaign_id'], ':reference' => $reference, ':provider' => $provider, ':currency' => $order['currency'], ':amount' => $order['grand_total'], ':subtotal' => $order['subtotal'], ':shipping' => $order['shipping_total'], ':tax' => $order['tax_total'], ':discount' => $order['discount_total']]);
            $transactionId = (int) $this->pdo->lastInsertId();
            $this->pdo->prepare("UPDATE sales_orders SET status='Paid',paid_at=COALESCE(paid_at,NOW()) WHERE id=:id")->execute([':id' => $orderId]);
            if ($order['opportunity_id']) $this->pdo->prepare("UPDATE opportunities SET stage='Won',won_at=COALESCE(won_at,NOW()),lost_at=NULL,lost_reason=NULL WHERE id=:id")->execute([':id' => $order['opportunity_id']]);
            $this->pdo->prepare("UPDATE email_leads SET lead_status='Converted' WHERE id=:id")->execute([':id' => $order['lead_id']]);
            $this->logActivity((int) $order['lead_id'], 'transaction_created', 'Payment recorded and transaction created', $reference, $order['opportunity_id'] ? (int) $order['opportunity_id'] : null, $orderId, $order['originating_campaign_id'] ? (int) $order['originating_campaign_id'] : null, ['transaction_id' => $transactionId]);
            $this->audit('transaction_created', 'transaction', $transactionId, null, ['sales_order_id' => $orderId, 'reference' => $reference]);
            $this->commitUnitOfWork($ownsTransaction);
            return $transactionId;
        } catch (Throwable $e) {
            $this->rollbackUnitOfWork($ownsTransaction);
            throw $e;
        }
    }

    private function leadForUpdate(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM email_leads WHERE id=:id AND archived_at IS NULL FOR UPDATE');
        $stmt->execute([':id' => $id]);
        $lead = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$lead) throw new InvalidArgumentException('Lead not found.');
        return $lead;
    }

    private function beginUnitOfWork(): bool
    {
        if ($this->pdo->inTransaction()) return false;
        $this->pdo->beginTransaction();
        return true;
    }

    private function commitUnitOfWork(bool $ownsTransaction): void
    {
        if ($ownsTransaction && $this->pdo->inTransaction()) $this->pdo->commit();
    }

    private function rollbackUnitOfWork(bool $ownsTransaction): void
    {
        if ($ownsTransaction && $this->pdo->inTransaction()) $this->pdo->rollBack();
    }
}
