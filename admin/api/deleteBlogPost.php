<?php

require_once 'blog_helpers.php';
requireAdminBlogJson();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendBlogJson([
        'success' => false,
        'message' => 'Blog posts can only be deleted with POST.'
    ], 405);
}

try {
    $payload = json_decode(file_get_contents('php://input'), true);
    $id = $payload['id'] ?? $_POST['id'] ?? null;

    if ($id === null || !ctype_digit((string) $id) || (int) $id <= 0) {
        throw new InvalidArgumentException('A valid blog post ID is required.');
    }

    $stmt = $pdo->prepare("
        DELETE FROM blog_posts
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([
        ':id' => (int) $id
    ]);

    if ($stmt->rowCount() === 0) {
        sendBlogJson([
            'success' => false,
            'message' => 'Blog post not found.'
        ], 404);
    }

    sendBlogJson([
        'success' => true,
        'message' => 'Blog post deleted.'
    ]);
} catch (Throwable $e) {
    $status = $e instanceof InvalidArgumentException ? 422 : 500;

    sendBlogJson([
        'success' => false,
        'message' => $e->getMessage()
    ], $status);
}
