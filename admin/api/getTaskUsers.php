<?php

require_once 'task_helpers.php';
requireAdminTaskJson();
require_once 'db.php';

try {
    $stmt = $pdo->query("
        SELECT
            u.id,
            u.first_name,
            u.last_name,
            CONCAT(u.first_name, ' ', u.last_name) AS full_name,
            u.email_address,
            u.visible,
            u.is_active
        FROM users u
        INNER JOIN user_roles ur ON ur.id = u.user_role_id
        WHERE ur.role_name = 'admin'
        ORDER BY u.first_name, u.last_name, u.email_address
    ");

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) {
    sendTaskJson([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
}
