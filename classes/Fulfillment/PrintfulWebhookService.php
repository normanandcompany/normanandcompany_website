<?php

declare(strict_types=1);

final class PrintfulWebhookService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly PrintfulClient $printful,
        private readonly FulfillmentCoordinator $coordinator
    ) {
    }

    public function handle(string $rawBody): array
    {
        if ($rawBody === '' || strlen($rawBody) > 1048576) {
            throw new InvalidArgumentException('Invalid webhook body.');
        }
        $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload) || empty($payload['type']) || !isset($payload['data'])) {
            throw new InvalidArgumentException('Invalid Printful webhook payload.');
        }

        $eventHash = hash('sha256', $rawBody);
        $eventType = mb_substr((string) $payload['type'], 0, 100);
        $externalOrderId = $this->extractOrderId($payload);
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO fulfillment_webhook_events (provider, event_hash, event_type, external_order_id) VALUES ('printful', :hash, :type, :order_id)");
        $stmt->execute([':hash' => $eventHash, ':type' => $eventType, ':order_id' => $externalOrderId]);
        if ($stmt->rowCount() === 0) {
            return ['success' => true, 'duplicate' => true];
        }
        $eventId = (int) $this->pdo->lastInsertId();

        try {
            if ($externalOrderId !== null) {
                // Stable v1 webhooks are notifications, not trusted state. Always re-fetch.
                $response = $this->printful->order($externalOrderId);
                $order = $response['result'] ?? null;
                if (!is_array($order)) {
                    throw new RuntimeException('Printful did not return the authoritative order.');
                }
                $this->coordinator->synchronizePrintfulOrder($order);
            }
            $stmt = $this->pdo->prepare("UPDATE fulfillment_webhook_events SET processing_status = 'processed', processed_at = NOW(), failure_message = NULL WHERE id = :id");
            $stmt->execute([':id' => $eventId]);
            return ['success' => true, 'duplicate' => false, 'event_type' => $eventType];
        } catch (Throwable $exception) {
            $stmt = $this->pdo->prepare("UPDATE fulfillment_webhook_events SET processing_status = 'failed', failure_message = :message WHERE id = :id");
            $stmt->execute([':message' => mb_substr($exception->getMessage(), 0, 2000), ':id' => $eventId]);
            throw $exception;
        }
    }

    private function extractOrderId(array $payload): ?string
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $candidates = [
            $data['order']['id'] ?? null,
            $data['shipment']['order_id'] ?? null,
            $data['order_id'] ?? null,
            $data['id'] ?? null
        ];
        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '') return $candidate;
        }
        return null;
    }
}
