<?php

require_once 'db.php';

header('Content-Type: application/json');

function productDetailsSelectSql(string $whereClause): string
{
    return "
        SELECT
            p.id,
            p.is_apparel,
            p.product_category_id,
            p.vendor_id,
            p.product_name,
            p.product_description,
            p.long_description,
            p.sku,
            p.asin,
            p.price,
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
        WHERE {$whereClause}
    ";
}

function incrementProductView(PDO $pdo, int $productId): void
{
    $stmt = $pdo->prepare("
        INSERT INTO product_views (
            product_id,
            view_count,
            first_viewed_at,
            last_viewed_at
        )
        VALUES (
            :product_id,
            1,
            NOW(),
            NOW()
        )
        ON DUPLICATE KEY UPDATE
            view_count = view_count + 1,
            last_viewed_at = VALUES(last_viewed_at)
    ");

    $stmt->execute([
        ':product_id' => $productId
    ]);
}

try {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($id <= 0) {
        http_response_code(422);
        echo json_encode([
            'error' => 'Invalid product ID'
        ]);
        exit;
    }

    $stmt = $pdo->prepare(productDetailsSelectSql('p.id = :id AND p.visible = 1 AND p.is_active = 1') . ' LIMIT 1');
    $stmt->execute([
        ':id' => $id
    ]);

    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    $formatOptions = [];

    if ($product && (int) ($product['product_category_id'] ?? 0) === 9) {
        $formatStmt = $pdo->prepare(
            productDetailsSelectSql(
                'p.product_category_id = 9
                AND p.product_name = :product_name
                AND p.format_id IS NOT NULL
                AND p.visible = 1
                AND p.is_active = 1'
            ) . ' ORDER BY bf.id ASC, p.id ASC'
        );
        $formatStmt->execute([
            ':product_name' => $product['product_name']
        ]);

        $formatOptions = $formatStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($product) {
        incrementProductView($pdo, (int) $product['id']);
    }

    echo json_encode([
        'product' => $product ?: null,
        'format_options' => $formatOptions
    ]);
} catch (Throwable $e) {
    error_log('Public product details failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Product details are temporarily unavailable.'
    ]);
}
