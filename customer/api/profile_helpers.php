<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';

function sendCustomerProfileJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function requireCustomerProfileJson(): int
{
    if (!isLoggedIn()) {
        sendCustomerProfileJson([
            'success' => false,
            'message' => 'You must be logged in to view your profile.'
        ], 401);
    }

    if (getUserRole() !== 'customer') {
        sendCustomerProfileJson([
            'success' => false,
            'message' => 'You do not have access to this customer profile.'
        ], 403);
    }

    return (int) getUserId();
}

function customerProfileCsrfToken(): string
{
    if (empty($_SESSION['customer_profile_csrf'])) {
        $_SESSION['customer_profile_csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['customer_profile_csrf'];
}

function requireCustomerProfileCsrf(): void
{
    $providedToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $sessionToken = (string) ($_SESSION['customer_profile_csrf'] ?? '');

    if ($providedToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $providedToken)) {
        sendCustomerProfileJson([
            'success' => false,
            'message' => 'Your session token is invalid. Refresh the profile and try again.'
        ], 403);
    }
}

function customerFavoriteTypes(): array
{
    return [
        'cruise_line',
        'destination',
        'excursion',
        'port',
        'itinerary',
        'ship'
    ];
}

function customerFavoriteType(string $type): string
{
    if (!in_array($type, customerFavoriteTypes(), true)) {
        throw new InvalidArgumentException('Select a valid favorite category.');
    }

    return $type;
}
