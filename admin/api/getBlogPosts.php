<?php

header('Content-Type: application/json');

require_once 'blog_helpers.php';
requireAdminBlogJson();
require_once 'db.php';

try {
    $stmt = $pdo->prepare("
        SELECT
            bp.id,
            bp.blog_category_id,
            bp.author_user_id,
            bp.post_title,
            bp.post_excerpt,
            bp.post_content,
            bp.featured_image_url,
            bp.seo_slug,
            bp.meta_title,
            bp.meta_description,
            bp.published_at,
            bp.visible,
            bp.is_featured,
            bp.created_at,
            bp.updated_at,
            bp.image2_url,
            bp.image3_url,
            bp.image4_url,
            bp.image5_url,
            bc.category_name,
            TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS author_full_name,
            u.email_address AS author_email
        FROM blog_posts bp
        LEFT JOIN blog_categories bc ON bc.id = bp.blog_category_id
        LEFT JOIN users u ON u.id = bp.author_user_id
        ORDER BY
            COALESCE(bp.published_at, bp.created_at) DESC,
            bp.id DESC
    ");
    $stmt->execute();

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) {
    sendBlogJson([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
}
