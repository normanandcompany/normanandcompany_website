<?php

header('Content-Type: application/json');

require_once 'db.php';

try {
    $stmt = $pdo->prepare("CALL sp_get_all_blog_posts()");

    $stmt->execute();

    $blogPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($blogPosts);

    $stmt->closeCursor();

} catch (Exception $e) {
    error_log('Public blog post list failed: ' . $e->getMessage());
    http_response_code(500);

    echo json_encode([
        "error" => "Blog posts are temporarily unavailable."
    ]);
}
?>
