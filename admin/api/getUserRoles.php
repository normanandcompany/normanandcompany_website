<?php

header('Content-Type: application/json');

require_once 'db.php';

function sendUserRoleError(string $message, int $statusCode = 500): void
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
            role_name,
            role_description,
            visible,
            is_active
        FROM user_roles
        WHERE COALESCE(is_active, 1) = 1
        ORDER BY role_name
    ");

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) {
    sendUserRoleError($e->getMessage());
}
