<?php

require_once 'product_helpers.php';
requireAdminJson();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendProductJson([
        'success' => false,
        'message' => 'Products can only be deleted with POST.'
    ], 405);
}

try {
    $payload = json_decode(file_get_contents('php://input'), true);
    $id = $payload['id'] ?? $_POST['id'] ?? null;

    if ($id === null || !ctype_digit((string) $id) || (int) $id <= 0) {
        throw new InvalidArgumentException('A valid product ID is required.');
    }

    $stmt = $pdo->prepare("
        DELETE FROM products
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([
        ':id' => (int) $id
    ]);

    if ($stmt->rowCount() === 0) {
        sendProductJson([
            'success' => false,
            'message' => 'Product not found.'
        ], 404);
    }

    sendProductJson([
        'success' => true,
        'message' => 'Product deleted.'
    ]);
} catch (Throwable $e) {
    $status = $e instanceof InvalidArgumentException ? 422 : 500;

    sendProductJson([
        'success' => false,
        'message' => $e->getMessage()
    ], $status);
}
