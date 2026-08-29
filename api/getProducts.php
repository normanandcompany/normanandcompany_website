<?php

header('Content-Type: application/json');

require_once 'db.php';

function sendProductsError(string $message, int $statusCode = 500): void
{
    http_response_code($statusCode);
    echo json_encode([
        'error' => $message
    ]);
    exit;
}

try {
    $categoryId = $_GET['product_category_id'] ?? $_GET['category_id'] ?? null;
    $params = [];

    $where = [
        'p.visible = 1',
        'p.is_active = 1'
    ];

    $bookRepresentativeCondition = "
        p.id = (
            SELECT representative.id
            FROM products representative
            LEFT JOIN book_formats representative_format
                ON representative_format.id = representative.format_id
            WHERE representative.product_category_id = 9
                AND representative.product_name = p.product_name
                AND representative.visible = 1
                AND representative.is_active = 1
            ORDER BY
                CASE
                    WHEN LOWER(REPLACE(COALESCE(representative_format.format_name, ''), '-', '')) = 'ebook'
                        THEN 0
                    ELSE 1
                END,
                representative.id ASC
            LIMIT 1
        )
    ";

    if ($categoryId !== null && $categoryId !== '' && $categoryId !== 'all') {
        if (!ctype_digit((string) $categoryId)) {
            sendProductsError('Invalid product category.', 422);
        }

        $where[] = 'p.product_category_id = :category_id';
        $params[':category_id'] = (int) $categoryId;

        if ((int) $categoryId === 9) {
            $where[] = $bookRepresentativeCondition;
        }
    } else {
        $where[] = "(p.product_category_id IS NULL OR p.product_category_id <> 9 OR {$bookRepresentativeCondition})";
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
            COALESCE((SELECT MIN(p.price + COALESCE(pv_price.price_adjustment, 0)) FROM product_variants pv_price WHERE pv_price.product_id = p.id AND pv_price.is_active = 1), p.price) AS minimum_price,
            COALESCE((SELECT MAX(p.price + COALESCE(pv_price.price_adjustment, 0)) FROM product_variants pv_price WHERE pv_price.product_id = p.id AND pv_price.is_active = 1), p.price) AS maximum_price,
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
            pc.category_name,
            bf.format_name
        FROM products p
        LEFT JOIN product_categories pc ON pc.id = p.product_category_id
        LEFT JOIN book_formats bf ON bf.id = p.format_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY p.created_at DESC, p.id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) {
    error_log('Public product list failed: ' . $e->getMessage());
    sendProductsError('Products are temporarily unavailable.');
}
