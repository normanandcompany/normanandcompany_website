<?php

require_once 'product_helpers.php';
requireAdminJson();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendProductJson([
        'success' => false,
        'message' => 'Products can only be saved with POST.'
    ], 405);
}

$uploadedFiles = [];

try {
    $id = productIntOrNull($_POST['id'] ?? '', 'Product ID') ?? 0;
    $productName = trim((string) ($_POST['product_name'] ?? ''));
    $categoryId = productIntOrNull($_POST['product_category_id'] ?? '', 'Product category');
    $price = productDecimalOrNull($_POST['price'] ?? '');
    $cost = productDecimalOrNull($_POST['cost'] ?? '');
    $inventoryCount = productIntOrNull($_POST['inventory_count'] ?? '', 'Inventory');
    $formatId = productIntOrNull($_POST['format_id'] ?? '', 'Book format');
    $seoSlug = productStringOrNull($_POST['seo_slug'] ?? '');

    if ($productName === '') {
        throw new InvalidArgumentException('Product name is required.');
    }

    if ($categoryId === null) {
        throw new InvalidArgumentException('Product category is required.');
    }

    if ($price === null) {
        throw new InvalidArgumentException('Product price is required.');
    }

    $categoryCheck = $pdo->prepare("
        SELECT category_name
        FROM product_categories
        WHERE id = :id
    ");
    $categoryCheck->execute([':id' => $categoryId]);
    $category = $categoryCheck->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        throw new InvalidArgumentException('Selected product category does not exist.');
    }

    $isBookCategory = $categoryId === 9
        || strtolower(trim((string) ($category['category_name'] ?? ''))) === 'books';

    if ($isBookCategory) {
        if ($formatId === null) {
            throw new InvalidArgumentException('Book format is required for book products.');
        }

        $formatCheck = $pdo->prepare("
            SELECT COUNT(*)
            FROM book_formats
            WHERE id = :id
        ");
        $formatCheck->execute([':id' => $formatId]);

        if ((int) $formatCheck->fetchColumn() === 0) {
            throw new InvalidArgumentException('Selected book format does not exist.');
        }
    } else {
        $formatId = null;
    }

    if ($id > 0) {
        $productCheck = $pdo->prepare("
            SELECT COUNT(*)
            FROM products
            WHERE id = :id
        ");
        $productCheck->execute([':id' => $id]);

        if ((int) $productCheck->fetchColumn() === 0) {
            sendProductJson([
                'success' => false,
                'message' => 'Product not found.'
            ], 404);
        }
    }

    $rawImages = [];

    for ($slot = 1; $slot <= 10; $slot++) {
        $imageName = productExistingImageName($_POST["existing_image_{$slot}"] ?? '');
        $fileField = "product_image_{$slot}";

        if (productHasUpload($fileField)) {
            $imageName = saveProductImageUpload($fileField, $productName, $slot, $uploadedFiles);
        }

        if ($imageName !== null) {
            $rawImages[] = $imageName;
        }
    }

    $imageFields = productImageFields();
    $images = [];

    foreach ($imageFields as $index => $field) {
        $images[$field] = $rawImages[$index] ?? null;
    }

    $data = [
        'is_apparel' => isset($_POST['is_apparel']) ? 1 : 0,
        'product_category_id' => $categoryId,
        'product_name' => $productName,
        'product_description' => productStringOrNull($_POST['product_description'] ?? ''),
        'long_description' => productStringOrNull($_POST['long_description'] ?? ''),
        'price' => $price,
        'cost' => $cost,
        'inventory_count' => $inventoryCount ?? 0,
        'format_id' => $formatId,
        'asin' => productStringOrNull($_POST['asin'] ?? ''),
        'isbn' => productStringOrNull($_POST['isbn'] ?? ''),
        'seo_slug' => $seoSlug ?: productSlug($productName),
        'meta_title' => productStringOrNull($_POST['meta_title'] ?? ''),
        'meta_description' => productStringOrNull($_POST['meta_description'] ?? ''),
        'visible' => isset($_POST['visible']) ? 1 : 0,
        'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'is_multi_image' => count($rawImages) > 1 ? 1 : 0
    ];

    $data = array_merge($data, $images);

    $pdo->beginTransaction();

    if ($id > 0) {
        $assignments = [];

        foreach (array_keys($data) as $column) {
            $assignments[] = "{$column} = :{$column}";
        }

        $sql = "
            UPDATE products
            SET " . implode(', ', $assignments) . "
            WHERE id = :id
        ";

        $stmt = $pdo->prepare($sql);
        $data['id'] = $id;
        $stmt->execute($data);
        $savedId = $id;
    } else {
        $columns = array_keys($data);
        $placeholders = array_map(fn($column) => ":{$column}", $columns);

        $sql = "
            INSERT INTO products (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $placeholders) . ")
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);
        $savedId = (int) $pdo->lastInsertId();
    }

    $pdo->commit();

    sendProductJson([
        'success' => true,
        'id' => $savedId,
        'message' => $id > 0 ? 'Product updated.' : 'Product added.'
    ]);
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    cleanupUploadedProductFiles($uploadedFiles);

    $status = $e instanceof InvalidArgumentException ? 422 : 500;

    sendProductJson([
        'success' => false,
        'message' => $e->getMessage()
    ], $status);
}
