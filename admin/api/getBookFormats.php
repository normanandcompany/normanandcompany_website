<?php

header('Content-Type: application/json');

require_once 'db.php';

function sendBookFormatError(string $message, int $statusCode = 500): void
{
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'message' => $message
    ]);
    exit;
}

try {
    $stmt = $pdo->query("
        SELECT
            id,
            format_name
        FROM book_formats
        ORDER BY format_name
    ");

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) {
    sendBookFormatError($e->getMessage());
}
