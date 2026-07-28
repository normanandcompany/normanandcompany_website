<?php

require_once __DIR__ . '/../../config/env.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/../../api/auth.php';

    header('Content-Type: application/json');

    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Administrator login required.'
        ]);
        exit;
    }

    if (getUserRole() !== 'admin') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Administrator access required.'
        ]);
        exit;
    }
}

try {
    $pdo = normanCreateDatabaseConnection('admin');

} catch(Throwable $e) {
    error_log('Administrator database connection failed: ' . $e->getMessage());
    http_response_code(500);
    die(json_encode([
        "success" => false,
        "message" => "The administrator service is temporarily unavailable."
    ]));
}
