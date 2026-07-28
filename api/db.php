<?php

require_once __DIR__ . '/../config/env.php';

try {
    $pdo = normanCreateDatabaseConnection('web');

} catch(Throwable $e) {
    error_log('Website database connection failed: ' . $e->getMessage());
    http_response_code(500);
    die(json_encode([
        "success" => false,
        "message" => "The website service is temporarily unavailable."
    ]));
}
