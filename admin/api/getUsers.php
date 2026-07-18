<?php

header('Content-Type: application/json');

require_once 'db.php';

function sendUserListError(string $message, int $statusCode = 500): void
{
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'message' => $message
    ]);
    exit;
}

try {
    $roleId = $_GET['user_role_id'] ?? $_GET['role_id'] ?? null;
    $params = [];
    $where = '';

    if ($roleId !== null && $roleId !== '' && $roleId !== 'all') {
        if (!ctype_digit((string) $roleId)) {
            sendUserListError('Invalid user role.', 422);
        }

        $where = 'WHERE u.user_role_id = :role_id';
        $params[':role_id'] = (int) $roleId;
    }

    $sql = "
        SELECT
            u.id,
            u.user_role_id,
            u.first_name,
            u.last_name,
            CONCAT(u.first_name, ' ', u.last_name) AS full_name,
            u.email_address,
            u.phone,
            u.address_1,
            u.address_2,
            u.city,
            u.state_prov_id,
            sp.name AS state_province,
            u.postal_code,
            u.country,
            u.last_login_at,
            u.visible,
            u.is_active,
            u.created_at,
            u.updated_at,
            ur.role_name,
            ur.role_description
        FROM users u
        LEFT JOIN user_roles ur ON ur.id = u.user_role_id
        LEFT JOIN state_prov sp ON sp.id = u.state_prov_id
        {$where}
        ORDER BY u.created_at DESC, u.id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) {
    sendUserListError($e->getMessage());
}
