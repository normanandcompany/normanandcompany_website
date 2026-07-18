<?php

header('Content-Type: application/json');

require_once 'db.php';

function sendProductCategoryError(string $message, int $statusCode = 500): void
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
            category_name,
            category_description,
            visible,
            is_active
        FROM product_categories
        WHERE COALESCE(is_active, 1) = 1
        ORDER BY category_name
    ");

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) {
    sendProductCategoryError($e->getMessage());
}
