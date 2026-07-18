<?php

require_once 'user_helpers.php';
requireAdminUserJson();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendUserJson([
        'success' => false,
        'message' => 'Users can only be deleted with POST.'
    ], 405);
}

try {
    $payload = json_decode(file_get_contents('php://input'), true);
    $id = $payload['id'] ?? $_POST['id'] ?? null;

    if ($id === null || !ctype_digit((string) $id) || (int) $id <= 0) {
        throw new InvalidArgumentException('A valid user ID is required.');
    }

    $id = (int) $id;

    if ($id === (int) getUserId()) {
        throw new InvalidArgumentException('You cannot delete the account you are currently using.');
    }

    if (!userRecordExists($pdo, 'users', $id)) {
        sendUserJson([
            'success' => false,
            'message' => 'User not found.'
        ], 404);
    }

    $pdo->beginTransaction();

    $logDelete = $pdo->prepare("
        DELETE FROM user_login_logs
        WHERE user_id = :id
    ");
    $logDelete->execute([':id' => $id]);

    $userDelete = $pdo->prepare("
        DELETE FROM users
        WHERE id = :id
        LIMIT 1
    ");
    $userDelete->execute([':id' => $id]);

    $pdo->commit();

    sendUserJson([
        'success' => true,
        'message' => 'User deleted.'
    ]);
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $status = $e instanceof InvalidArgumentException ? 422 : 500;

    sendUserJson([
        'success' => false,
        'message' => $e->getMessage()
    ], $status);
}
