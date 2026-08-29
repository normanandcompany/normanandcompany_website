<?php

declare(strict_types=1);

final class PrintfulClient
{
    private const BASE_URL = 'https://api.printful.com';

    public function __construct(
        private readonly PrintfulConfig $config,
        private readonly ?PDO $pdo = null
    ) {
    }

    public function store(): array
    {
        return $this->request('GET', '/store');
    }

    public function listSyncProducts(): array
    {
        $all = [];
        $offset = 0;

        do {
            $response = $this->request('GET', '/store/products?limit=100&offset=' . $offset);
            $items = is_array($response['result'] ?? null) ? $response['result'] : [];
            $all = array_merge($all, $items);
            $total = (int) ($response['paging']['total'] ?? count($all));
            $offset += count($items);
        } while ($items !== [] && $offset < $total);

        return $all;
    }

    public function syncProduct(string|int $id): array
    {
        return $this->request('GET', '/store/products/' . rawurlencode((string) $id));
    }

    public function shippingRates(array $recipient, array $items): array
    {
        return $this->request('POST', '/shipping/rates', [
            'recipient' => $recipient,
            'items' => $items
        ]);
    }

    public function createDraftOrder(array $order): array
    {
        return $this->request('POST', '/orders?confirm=0', $order);
    }

    public function order(string|int $id): array
    {
        return $this->request('GET', '/orders/' . rawurlencode((string) $id));
    }

    public function orderByExternalReference(string $reference): array
    {
        return $this->order('@' . $reference);
    }

    public function confirmOrder(string|int $id): array
    {
        return $this->request('POST', '/orders/' . rawurlencode((string) $id) . '/confirm', []);
    }

    public function webhookConfiguration(): array
    {
        return $this->request('GET', '/webhooks');
    }

    private function request(string $method, string $path, ?array $body = null): array
    {
        if (!function_exists('curl_init')) {
            throw new PrintfulException('The PHP cURL extension is required for Printful.', 'configuration');
        }

        $attempt = 0;
        $maximumAttempts = in_array($method, ['GET'], true) ? 3 : 2;

        while (true) {
            $attempt++;

            try {
                return $this->performRequest($method, $path, $body);
            } catch (PrintfulException $exception) {
                $retryable = in_array($exception->category(), ['network', 'rate_limit', 'server_error'], true);

                if (!$retryable || $attempt >= $maximumAttempts) {
                    throw $exception;
                }

                usleep(150000 * $attempt);
            }
        }
    }

    private function performRequest(string $method, string $path, ?array $body): array
    {
        $headers = [
            'Authorization: Bearer ' . $this->config->token,
            'Accept: application/json',
            'Content-Type: application/json'
        ];

        if ($this->config->storeId !== null) {
            $headers[] = 'X-PF-Store-Id: ' . $this->config->storeId;
        }

        $handle = curl_init(self::BASE_URL . $path);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => min(10, $this->config->timeoutSeconds),
            CURLOPT_TIMEOUT => $this->config->timeoutSeconds,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0
        ]);

        if ($body !== null && $method !== 'GET') {
            $json = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            curl_setopt($handle, CURLOPT_POSTFIELDS, $json);
        }

        $raw = curl_exec($handle);
        $curlError = curl_error($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($raw === false || $curlError !== '') {
            $this->recordHealth(false, null, 'network', 'Printful network request failed.');
            throw new PrintfulException('Printful could not be reached. Please try again.', 'network');
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->recordHealth(false, $status, 'invalid_response', 'Printful returned invalid JSON.');
            throw new PrintfulException('Printful returned an invalid response.', 'invalid_response', $status);
        }

        if ($status < 200 || $status >= 300) {
            $category = match (true) {
                in_array($status, [401, 403], true) => 'authentication',
                $status === 404 => 'not_found',
                $status === 429 => 'rate_limit',
                $status >= 500 => 'server_error',
                default => 'validation'
            };
            $messageValue = $decoded['error']['message'] ?? $decoded['result'] ?? $decoded['error'] ?? '';
            $message = is_scalar($messageValue) ? trim((string) $messageValue) : '';
            $safeMessage = $message !== '' ? $message : 'Printful rejected the request.';
            $this->recordHealth(false, $status, $category, $safeMessage);
            throw new PrintfulException($safeMessage, $category, $status, $decoded);
        }

        $this->recordHealth(true, $status, null, null);
        return is_array($decoded) ? $decoded : [];
    }

    private function recordHealth(bool $success, ?int $status, ?string $category, ?string $message): void
    {
        if (!$this->pdo) {
            return;
        }

        try {
            $sql = $success
                ? "INSERT INTO fulfillment_api_health (provider, last_success_at, last_status_code, last_error_category, last_error_message) VALUES ('printful', NOW(), :status, NULL, NULL) ON DUPLICATE KEY UPDATE last_success_at = NOW(), last_status_code = VALUES(last_status_code), last_error_category = NULL, last_error_message = NULL"
                : "INSERT INTO fulfillment_api_health (provider, last_failure_at, last_status_code, last_error_category, last_error_message) VALUES ('printful', NOW(), :status, :category, :message) ON DUPLICATE KEY UPDATE last_failure_at = NOW(), last_status_code = VALUES(last_status_code), last_error_category = VALUES(last_error_category), last_error_message = VALUES(last_error_message)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':status' => $status,
                ...($success ? [] : [':category' => $category, ':message' => mb_substr((string) $message, 0, 500)])
            ]);
        } catch (Throwable) {
            // Diagnostics must never mask the API result.
        }
    }
}
