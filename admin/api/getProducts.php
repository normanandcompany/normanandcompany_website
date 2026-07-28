<?php

header('Content-Type: application/json');

require_once 'db.php';

function sendProductListError(string $message, int $statusCode = 500): void
{
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'message' => $message
    ]);
    exit;
}

try {
    $categoryId = $_GET['product_category_id'] ?? $_GET['category_id'] ?? null;
    $params = [];
    $where = '';

    if ($categoryId !== null && $categoryId !== '' && $categoryId !== 'all') {
        if (!ctype_digit((string) $categoryId)) {
            sendProductListError('Invalid product category.', 422);
        }

        $where = 'WHERE p.product_category_id = :category_id';
        $params[':category_id'] = (int) $categoryId;
    }

    $sql = "
        SELECT
            p.id,
            p.is_apparel,
            p.product_category_id,
            p.vendor_id,
            p.product_name,
            p.product_description,
            p.long_description,
            p.sku,
            p.price,
            p.cost,
            p.inventory_count,
            p.image_url,
            p.seo_slug,
            p.meta_title,
            p.meta_description,
            p.visible,
            p.is_featured,
            p.is_active,
            p.created_at,
            p.updated_at,
            p.is_multi_image,
            p.image_url2,
            p.image_url3,
            p.image_url4,
            p.image_url5,
            p.image_url6,
            p.image_url7,
            p.image_url8,
            p.image_url9,
            p.image_url10,
            p.format_id,
            p.asin,
            p.isbn,
            pc.category_name,
            bf.format_name
        FROM products p
        LEFT JOIN product_categories pc ON pc.id = p.product_category_id
        LEFT JOIN book_formats bf ON bf.id = p.format_id
        {$where}
        ORDER BY p.created_at DESC, p.id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) {
    sendProductListError($e->getMessage());
}
