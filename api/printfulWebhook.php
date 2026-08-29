<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../classes/Fulfillment/bootstrap.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new InvalidArgumentException('POST required.');
    }
    $config = PrintfulConfig::fromEnvironment();
    if ($config->webhookSecret === '') {
        throw new RuntimeException('Printful webhook handling is not configured.');
    }
    $providedSecret = (string) ($_SERVER['HTTP_X_NORMAN_PRINTFUL_SECRET'] ?? $_GET['key'] ?? '');
    if ($providedSecret === '' || !hash_equals($config->webhookSecret, $providedSecret)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
        exit;
    }
    $client = new PrintfulClient($config, $pdo);
    $coordinator = new FulfillmentCoordinator($pdo, $client, $config);
    $service = new PrintfulWebhookService($pdo, $client, $coordinator);
    echo json_encode($service->handle((string) file_get_contents('php://input')));
} catch (Throwable $exception) {
    $status = $exception instanceof InvalidArgumentException ? 422 : 500;
    if ($exception instanceof PrintfulException && in_array($exception->httpStatus(), [401, 403, 404, 429], true)) {
        $status = $exception->httpStatus();
    }
    if ($status === 500) error_log('Printful webhook failed: ' . $exception->getMessage());
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $status === 500 ? 'Webhook processing failed.' : $exception->getMessage()]);
}
