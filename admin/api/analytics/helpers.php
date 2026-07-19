<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';

function sendAnalyticsJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    header('Cache-Control: no-store, private');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function requireAdminAnalyticsJson(): void
{
    if (!isLoggedIn()) {
        sendAnalyticsJson([
            'success' => false,
            'data' => null,
            'meta' => [
                'source' => 'cloudflare'
            ],
            'warnings' => [],
            'error' => 'Authentication required.'
        ], 401);
    }

    if (getUserRole() !== 'admin') {
        sendAnalyticsJson([
            'success' => false,
            'data' => null,
            'meta' => [
                'source' => 'cloudflare'
            ],
            'warnings' => [],
            'error' => 'Administrative access required.'
        ], 403);
    }
}
