<?php

header('Content-Type: application/json');

require_once 'blog_helpers.php';
requireAdminBlogJson();
require_once 'db.php';

try {
    $stmt = $pdo->prepare("
        SELECT
            id,
            category_name,
            category_description,
            seo_slug,
            visible,
            is_active,
            created_at,
            updated_at
        FROM blog_categories
        ORDER BY category_name
    ");
    $stmt->execute();

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) {
    sendBlogJson([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
}
