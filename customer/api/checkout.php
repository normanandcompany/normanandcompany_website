<?php

declare(strict_types=1);

require_once __DIR__ . '/profile_helpers.php';
$userId = requireCustomerProfileJson();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../../classes/Fulfillment/bootstrap.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->prepare('SELECT first_name, last_name, email_address, phone, address_1, address_2, city, sp.name AS state_code, postal_code, country FROM users u LEFT JOIN state_prov sp ON sp.id = u.state_prov_id WHERE u.id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        sendCustomerProfileJson([
            'success' => true,
            'csrf_token' => customerProfileCsrfToken(),
            'profile' => $profile,
            'supported_countries' => ['US' => 'United States', 'CA' => 'Canada'],
            'payment_integration' => 'not_configured'
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendCustomerProfileJson(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
    requireCustomerProfileCsrf();
    $body = json_decode((string) file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($body)) {
        throw new InvalidArgumentException('Invalid checkout request.');
    }

    $config = PrintfulConfig::fromEnvironment(false);
    $service = new CheckoutService($pdo, new PrintfulClient($config, $pdo), $config);
    $action = (string) ($body['action'] ?? '');

    if ($action === 'quote') {
        sendCustomerProfileJson($service->quote($userId, (array) ($body['cart'] ?? []), (array) ($body['address'] ?? [])));
    }
    if ($action === 'prepare_order') {
        sendCustomerProfileJson($service->prepareOrder(
            $userId,
            (array) ($body['cart'] ?? []),
            (array) ($body['address'] ?? []),
            trim((string) ($body['quote_token'] ?? '')),
            trim((string) ($body['rate_id'] ?? ''))
        ));
    }
    throw new InvalidArgumentException('Unknown checkout action.');
} catch (Throwable $exception) {
    $status = $exception instanceof InvalidArgumentException ? 422 : 500;
    if ($exception instanceof PrintfulException) {
        $status = in_array($exception->httpStatus(), [401, 403, 429], true) ? (int) $exception->httpStatus() : ($exception->category() === 'validation' ? 422 : 503);
    }
    if ($status >= 500) error_log('Checkout preparation failed: ' . $exception->getMessage());
    sendCustomerProfileJson([
        'success' => false,
        'message' => $status >= 500 ? 'Checkout is temporarily unavailable. Please try again.' : $exception->getMessage()
    ], $status);
}
