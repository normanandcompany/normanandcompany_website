<?php

require_once 'task_helpers.php';
requireAdminTaskJson();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendTaskJson([
        'success' => false,
        'message' => 'Tasks can only be deleted with POST.'
    ], 405);
}

try {
    $payload = json_decode(file_get_contents('php://input'), true);
    $id = $payload['id'] ?? $_POST['id'] ?? null;

    if ($id === null || !ctype_digit((string) $id) || (int) $id <= 0) {
        throw new InvalidArgumentException('A valid task ID is required.');
    }

    $id = (int) $id;

    if (!taskRecordExists($pdo, 'tasks', $id)) {
        sendTaskJson([
            'success' => false,
            'message' => 'Task not found.'
        ], 404);
    }

    $stmt = $pdo->prepare("
        DELETE FROM tasks
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);

    sendTaskJson([
        'success' => true,
        'message' => 'Task deleted.'
    ]);
} catch (Throwable $e) {
    $statusCode = $e instanceof InvalidArgumentException ? 422 : 500;

    sendTaskJson([
        'success' => false,
        'message' => $e->getMessage()
    ], $statusCode);
}
