<?php

header('Content-Type: application/json');

require_once 'db.php';

try {
    $stmt = $pdo->prepare("CALL sp_get_all_blog_categories()");

    $stmt->execute();

    $blogCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($blogCategories);

    $stmt->closeCursor();

} catch (Exception $e) {
    error_log('Public blog category list failed: ' . $e->getMessage());
    http_response_code(500);

    echo json_encode([
        "error" => "Blog categories are temporarily unavailable."
    ]);
}
?>
