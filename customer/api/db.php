<?php

require_once __DIR__ . '/../../config/env.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/../../api/auth.php';

    header('Content-Type: application/json');

    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Customer login required.'
        ]);
        exit;
    }

    if (getUserRole() !== 'customer') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Customer access required.'
        ]);
        exit;
    }
}

try {
    $pdo = normanCreateDatabaseConnection('web');

} catch(Throwable $e) {
    error_log('Customer database connection failed: ' . $e->getMessage());
    http_response_code(500);
    die(json_encode([
        "success" => false,
        "message" => "The customer service is temporarily unavailable."
    ]));
}
