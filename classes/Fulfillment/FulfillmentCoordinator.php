<?php

declare(strict_types=1);

final class FulfillmentCoordinator
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly PrintfulClient $printful,
        private readonly PrintfulConfig $config
    ) {
    }

    public function processPaidOrder(int $orderId): array
    {
        if ($orderId <= 0) {
            throw new InvalidArgumentException('A valid order ID is required.');
        }

        $order = $this->authorizeAndPrepare($orderId);
        $fulfillments = $this->loadFulfillments($orderId);
        $results = [];

        foreach ($fulfillments as $fulfillment) {
            if ($fulfillment['fulfillment_provider'] !== 'printful') {
                $results[] = [
                    'vendor_id' => (int) $fulfillment['vendor_id'],
                    'provider' => $fulfillment['fulfillment_provider'] ?: 'manual',
                    'status' => 'pending'
                ];
                continue;
            }

            $results[] = $this->processPrintfulFulfillment($order, $fulfillment);
        }

        $this->refreshOrderFulfillmentStatus($orderId);
        return ['order_id' => $orderId, 'payment_status' => 'paid', 'fulfillments' => $results];
    }

    public function synchronizePrintfulOrder(array $authoritativeOrder): void
    {
        $externalId = trim((string) ($authoritativeOrder['id'] ?? ''));
        $externalReference = trim((string) ($authoritativeOrder['external_id'] ?? ''));

        if ($externalId === '' && $externalReference === '') {
            throw new InvalidArgumentException('The Printful order response has no identifier.');
        }

        $stmt = $this->pdo->prepare('SELECT vf.* FROM vendor_fulfillments vf INNER JOIN vendors v ON v.id = vf.vendor_id WHERE v.fulfillment_provider = \'printful\' AND (vf.external_order_id = :external_id OR vf.external_reference = :external_reference) LIMIT 1');
        $stmt->execute([':external_id' => $externalId, ':external_reference' => $externalReference]);
        $fulfillment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fulfillment) {
            throw new RuntimeException('No local fulfillment matches the Printful order.');
        }

        $status = trim((string) ($authoritativeOrder['status'] ?? 'unknown'));
        $localStatus = $this->localStatus($status, $authoritativeOrder);
        $stmt = $this->pdo->prepare('UPDATE vendor_fulfillments SET external_order_id = COALESCE(external_order_id, :external_id), vendor_order_status = :vendor_status, fulfillment_status = :status, last_synced_at = NOW(), last_error_code = NULL, last_error_message = NULL WHERE id = :id');
        $stmt->execute([
            ':external_id' => $externalId ?: null,
            ':vendor_status' => $status,
            ':status' => $localStatus,
            ':id' => $fulfillment['id']
        ]);

        $shipments = is_array($authoritativeOrder['shipments'] ?? null) ? $authoritativeOrder['shipments'] : [];
        foreach ($shipments as $index => $shipment) {
            $shipmentId = trim((string) ($shipment['id'] ?? $shipment['shipment_id'] ?? ''));
            if ($shipmentId === '') {
                $shipmentId = hash('sha256', json_encode($shipment, JSON_THROW_ON_ERROR) . ':' . $index);
            }
            $trackingNumber = trim((string) ($shipment['tracking_number'] ?? '')) ?: null;
            $trackingUrl = trim((string) ($shipment['tracking_url'] ?? '')) ?: null;
            if ($trackingUrl !== null && (!filter_var($trackingUrl, FILTER_VALIDATE_URL) || !in_array(strtolower((string) parse_url($trackingUrl, PHP_URL_SCHEME)), ['http', 'https'], true))) {
                $trackingUrl = null;
            }
            $carrier = trim((string) ($shipment['carrier'] ?? '')) ?: null;
            $service = trim((string) ($shipment['service'] ?? '')) ?: null;
            $shippedAt = $this->printfulDate($shipment['ship_date'] ?? $shipment['shipped_at'] ?? null);
            $estimatedAt = $this->printfulDate($shipment['estimated_delivery'] ?? $shipment['estimated_delivery_at'] ?? null);
            $shipmentStatus = !empty($shipment['delivered_at']) ? 'delivered' : ($shippedAt ? 'shipped' : 'pending');
            $stmt = $this->pdo->prepare("INSERT INTO fulfillment_shipments (vendor_fulfillment_id, external_shipment_id, shipment_status, carrier, service, tracking_number, tracking_url, shipped_at, estimated_delivery_at, delivered_at) VALUES (:fulfillment_id, :external_id, :status, :carrier, :service, :tracking_number, :tracking_url, :shipped_at, :estimated_at, :delivered_at) ON DUPLICATE KEY UPDATE shipment_status = VALUES(shipment_status), carrier = VALUES(carrier), service = VALUES(service), tracking_number = VALUES(tracking_number), tracking_url = VALUES(tracking_url), shipped_at = VALUES(shipped_at), estimated_delivery_at = VALUES(estimated_delivery_at), delivered_at = VALUES(delivered_at), id = LAST_INSERT_ID(id)");
            $stmt->execute([
                ':fulfillment_id' => $fulfillment['id'],
                ':external_id' => $shipmentId,
                ':status' => $shipmentStatus,
                ':carrier' => $carrier,
                ':service' => $service,
                ':tracking_number' => $trackingNumber,
                ':tracking_url' => $trackingUrl,
                ':shipped_at' => $shippedAt,
                ':estimated_at' => $estimatedAt,
                ':delivered_at' => $this->printfulDate($shipment['delivered_at'] ?? null)
            ]);
            $localShipmentId = (int) $this->pdo->lastInsertId();
            $shipmentItems = is_array($shipment['items'] ?? null) ? $shipment['items'] : [];
            foreach ($shipmentItems as $shipmentItem) {
                $externalItemId = trim((string) ($shipmentItem['item_id'] ?? $shipmentItem['id'] ?? ''));
                if ($externalItemId === '') continue;
                $itemStmt = $this->pdo->prepare('SELECT id FROM vendor_fulfillment_items WHERE vendor_fulfillment_id = :fulfillment_id AND external_line_item_id = :external_id LIMIT 1');
                $itemStmt->execute([':fulfillment_id' => $fulfillment['id'], ':external_id' => $externalItemId]);
                $localItemId = (int) $itemStmt->fetchColumn();
                if (!$localItemId) continue;
                $linkStmt = $this->pdo->prepare('INSERT INTO fulfillment_shipment_items (shipment_id, vendor_fulfillment_item_id, quantity) VALUES (:shipment_id, :item_id, :quantity) ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)');
                $linkStmt->execute([':shipment_id' => $localShipmentId, ':item_id' => $localItemId, ':quantity' => max(1, (int) ($shipmentItem['quantity'] ?? 1))]);
            }
        }

        $this->refreshOrderFulfillmentStatus((int) $fulfillment['order_id']);
    }

    private function authorizeAndPrepare(int $orderId): array
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                throw new InvalidArgumentException('Order not found.');
            }
            if (strtolower((string) $order['payment_status']) !== 'paid') {
                throw new RuntimeException('Fulfillment is blocked because the order is not paid.');
            }

            $stmt = $this->pdo->prepare('SELECT oi.*, p.vendor_id AS current_vendor_id FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = :order_id ORDER BY oi.id');
            $stmt->execute([':order_id' => $orderId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($items === []) {
                throw new RuntimeException('The order has no fulfillable items.');
            }

            $vendorIds = [];
            foreach ($items as $item) {
                $vendorId = (int) ($item['vendor_id'] ?: $item['current_vendor_id']);
                if ($vendorId <= 0) {
                    throw new RuntimeException('An order item has no fulfillment vendor.');
                }
                $vendorIds[$vendorId] = true;
                if (!(int) $item['vendor_id']) {
                    $update = $this->pdo->prepare('UPDATE order_items SET vendor_id = :vendor_id WHERE id = :id');
                    $update->execute([':vendor_id' => $vendorId, ':id' => $item['id']]);
                }
            }

            $insert = $this->pdo->prepare("INSERT INTO vendor_fulfillments (order_id, vendor_id, external_reference, fulfillment_status, currency_code) VALUES (:order_id, :vendor_id, :reference, 'pending', :currency) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)");
            foreach (array_keys($vendorIds) as $vendorId) {
                $insert->execute([
                    ':order_id' => $orderId,
                    ':vendor_id' => $vendorId,
                    ':reference' => ($order['order_number'] ?: 'ORDER-' . $orderId) . '-V' . $vendorId,
                    ':currency' => $order['currency_code'] ?: 'USD'
                ]);
            }

            $this->pdo->commit();
            return $order;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function loadFulfillments(int $orderId): array
    {
        $stmt = $this->pdo->prepare('SELECT vf.*, v.fulfillment_provider, v.vendor_name FROM vendor_fulfillments vf INNER JOIN vendors v ON v.id = vf.vendor_id WHERE vf.order_id = :order_id ORDER BY vf.id');
        $stmt->execute([':order_id' => $orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function processPrintfulFulfillment(array $order, array $fulfillment): array
    {
        if ($fulfillment['external_order_id']) {
            $response = $this->printful->order((string) $fulfillment['external_order_id']);
            $authoritative = $response['result'] ?? [];
            if (is_array($authoritative)) {
                $this->synchronizePrintfulOrder($authoritative);
            }
            return ['vendor_id' => (int) $fulfillment['vendor_id'], 'provider' => 'printful', 'status' => $fulfillment['fulfillment_status'], 'external_order_id' => $fulfillment['external_order_id'], 'idempotent' => true];
        }

        $items = $this->loadPrintfulItems((int) $order['id'], (int) $fulfillment['vendor_id']);
        $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM order_items WHERE order_id = :order_id AND vendor_id = :vendor_id');
        $countStmt->execute([':order_id' => $order['id'], ':vendor_id' => $fulfillment['vendor_id']]);
        $expectedItemCount = (int) $countStmt->fetchColumn();
        if ($items === [] || count($items) !== $expectedItemCount) {
            throw new RuntimeException('Every Printful order item must have an active Sync Variant mapping before fulfillment.');
        }

        $externalReference = (string) $fulfillment['external_reference'];
        $authoritative = null;
        try {
            $lookup = $this->printful->orderByExternalReference($externalReference);
            $authoritative = $lookup['result'] ?? null;
        } catch (PrintfulException $exception) {
            if (!$exception->isNotFound()) {
                $this->markFailure((int) $fulfillment['id'], $exception);
                throw $exception;
            }
        }

        try {
            if (!is_array($authoritative)) {
                $payload = [
                    'external_id' => $externalReference,
                    'shipping' => $fulfillment['shipping_service_id'] ?: null,
                    'recipient' => $this->recipient($order),
                    'items' => array_map(static fn(array $item): array => [
                        'sync_variant_id' => (int) $item['external_variant_id'],
                        'quantity' => (int) $item['quantity'],
                        'external_id' => 'OI-' . $item['id'],
                        'retail_price' => number_format((float) $item['unit_price'], 2, '.', ''),
                        'name' => $item['product_name']
                    ], $items)
                ];
                if ($payload['shipping'] === null) {
                    unset($payload['shipping']);
                }
                $created = $this->printful->createDraftOrder($payload);
                $authoritative = $created['result'] ?? null;
                if (!is_array($authoritative) || empty($authoritative['id'])) {
                    throw new PrintfulException('Printful did not return the created draft order.', 'invalid_response');
                }
            }

            $this->persistDraft($fulfillment, $items, $authoritative);

            if ($this->config->autoConfirm) {
                $this->assertStillPaid((int) $order['id']);
                $confirmed = $this->printful->confirmOrder((string) $authoritative['id']);
                $authoritative = is_array($confirmed['result'] ?? null) ? $confirmed['result'] : $authoritative;
                $stmt = $this->pdo->prepare("UPDATE vendor_fulfillments SET fulfillment_status = 'submitted', vendor_order_status = :status, confirmed_at = NOW(), last_synced_at = NOW() WHERE id = :id");
                $stmt->execute([':status' => $authoritative['status'] ?? 'pending', ':id' => $fulfillment['id']]);
            }

            $this->synchronizePrintfulOrder($authoritative);
            return [
                'vendor_id' => (int) $fulfillment['vendor_id'],
                'provider' => 'printful',
                'status' => $this->config->autoConfirm ? 'submitted' : 'draft',
                'external_order_id' => (string) $authoritative['id'],
                'auto_confirmed' => $this->config->autoConfirm
            ];
        } catch (Throwable $exception) {
            $this->markFailure((int) $fulfillment['id'], $exception);
            throw $exception;
        }
    }

    private function loadPrintfulItems(int $orderId, int $vendorId): array
    {
        $stmt = $this->pdo->prepare("SELECT oi.*, vvm.id AS mapping_id, COALESCE(vvm.external_variant_id, vpm.default_external_variant_id) AS external_variant_id FROM order_items oi INNER JOIN vendor_product_mappings vpm ON vpm.product_id = oi.product_id AND vpm.vendor_id = oi.vendor_id AND vpm.mapping_status = 'active' LEFT JOIN product_variants pv ON pv.id = oi.product_variant_id LEFT JOIN vendor_variant_mappings vvm ON vvm.product_variant_id = pv.id AND vvm.vendor_product_mapping_id = vpm.id AND vvm.is_active = 1 WHERE oi.order_id = :order_id AND oi.vendor_id = :vendor_id AND COALESCE(vvm.external_variant_id, vpm.default_external_variant_id) IS NOT NULL ORDER BY oi.id");
        $stmt->execute([':order_id' => $orderId, ':vendor_id' => $vendorId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function persistDraft(array $fulfillment, array $items, array $authoritative): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("UPDATE vendor_fulfillments SET external_order_id = :external_id, fulfillment_status = 'draft', vendor_order_status = :vendor_status, submitted_at = COALESCE(submitted_at, NOW()), last_synced_at = NOW(), last_error_code = NULL, last_error_message = NULL WHERE id = :id");
            $stmt->execute([':external_id' => $authoritative['id'], ':vendor_status' => $authoritative['status'] ?? 'draft', ':id' => $fulfillment['id']]);
            $remoteItemsByExternal = [];
            foreach ((array) ($authoritative['items'] ?? []) as $remoteItem) {
                $remoteItemsByExternal[(string) ($remoteItem['external_id'] ?? '')] = (string) ($remoteItem['id'] ?? '');
            }
            $insert = $this->pdo->prepare("INSERT INTO vendor_fulfillment_items (vendor_fulfillment_id, order_item_id, vendor_variant_mapping_id, external_line_item_id, quantity, status) VALUES (:fulfillment_id, :order_item_id, :mapping_id, :external_line_item_id, :quantity, 'submitted') ON DUPLICATE KEY UPDATE vendor_variant_mapping_id = VALUES(vendor_variant_mapping_id), external_line_item_id = COALESCE(VALUES(external_line_item_id), external_line_item_id), quantity = VALUES(quantity), status = VALUES(status)");
            foreach ($items as $item) {
                $remoteItemId = $remoteItemsByExternal['OI-' . $item['id']] ?? null;
                $insert->execute([':fulfillment_id' => $fulfillment['id'], ':order_item_id' => $item['id'], ':mapping_id' => $item['mapping_id'], ':external_line_item_id' => $remoteItemId ?: null, ':quantity' => $item['quantity']]);
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function assertStillPaid(int $orderId): void
    {
        $stmt = $this->pdo->prepare('SELECT payment_status FROM orders WHERE id = :id');
        $stmt->execute([':id' => $orderId]);
        if (strtolower((string) $stmt->fetchColumn()) !== 'paid') {
            throw new RuntimeException('Printful confirmation was blocked because the order is no longer paid.');
        }
    }

    private function markFailure(int $fulfillmentId, Throwable $exception): void
    {
        $category = $exception instanceof PrintfulException ? $exception->category() : 'application_error';
        $stmt = $this->pdo->prepare("UPDATE vendor_fulfillments SET fulfillment_status = 'error', last_error_code = :code, last_error_message = :message, retry_count = retry_count + 1, next_retry_at = CASE WHEN :retryable = 1 THEN DATE_ADD(NOW(), INTERVAL LEAST(60, POW(2, retry_count)) MINUTE) ELSE NULL END WHERE id = :id");
        $stmt->execute([
            ':code' => $category,
            ':message' => mb_substr($exception->getMessage(), 0, 2000),
            ':retryable' => in_array($category, ['network', 'rate_limit', 'server_error'], true) ? 1 : 0,
            ':id' => $fulfillmentId
        ]);
    }

    private function recipient(array $order): array
    {
        return array_filter([
            'name' => $order['shipping_name'], 'email' => $order['shipping_email'], 'phone' => $order['shipping_phone'],
            'address1' => $order['shipping_address1'], 'address2' => $order['shipping_address2'], 'city' => $order['shipping_city'],
            'state_code' => $order['shipping_state_code'], 'country_code' => $order['shipping_country_code'], 'zip' => $order['shipping_postal_code']
        ], static fn(mixed $value): bool => $value !== null && $value !== '');
    }

    private function localStatus(string $vendorStatus, array $order): string
    {
        if (!empty($order['shipments'])) {
            return in_array(strtolower($vendorStatus), ['fulfilled', 'completed'], true) ? 'fulfilled' : 'partially_shipped';
        }
        return match (strtolower($vendorStatus)) {
            'draft' => 'draft', 'pending', 'onhold' => 'submitted', 'inprocess' => 'in_progress',
            'fulfilled', 'completed' => 'fulfilled', 'canceled', 'cancelled' => 'cancelled', 'returned' => 'returned', 'failed' => 'error',
            default => 'submitted'
        };
    }

    private function refreshOrderFulfillmentStatus(int $orderId): void
    {
        $stmt = $this->pdo->prepare('SELECT fulfillment_status FROM vendor_fulfillments WHERE order_id = :order_id');
        $stmt->execute([':order_id' => $orderId]);
        $statuses = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $status = 'pending';
        if (in_array('error', $statuses, true)) $status = 'error';
        elseif ($statuses !== [] && count(array_intersect($statuses, ['fulfilled', 'shipped'])) === count($statuses)) $status = 'fulfilled';
        elseif (array_intersect($statuses, ['shipped', 'partially_shipped'])) $status = 'partially_shipped';
        elseif (array_intersect($statuses, ['submitted', 'in_progress'])) $status = 'in_progress';
        elseif (in_array('draft', $statuses, true)) $status = 'draft';
        $stmt = $this->pdo->prepare('UPDATE orders SET fulfillment_status = :status WHERE id = :id');
        $stmt->execute([':status' => $status, ':id' => $orderId]);
    }

    private function printfulDate(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        if (is_numeric($value)) return gmdate('Y-m-d H:i:s', (int) $value);
        try { return (new DateTimeImmutable((string) $value))->format('Y-m-d H:i:s'); } catch (Throwable) { return null; }
    }
}
