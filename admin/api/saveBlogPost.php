<?php

require_once 'blog_helpers.php';
requireAdminBlogJson();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendBlogJson([
        'success' => false,
        'message' => 'Blog posts can only be saved with POST.'
    ], 405);
}

$uploadedFiles = [];

try {
    $id = blogIntOrNull($_POST['id'] ?? '', 'Blog post ID') ?? 0;
    $postTitle = blogRequiredString($_POST['post_title'] ?? '', 'Post title');
    $categoryId = blogIntOrNull($_POST['blog_category_id'] ?? '', 'Blog category');

    if ($categoryId === null) {
        throw new InvalidArgumentException('Blog category is required.');
    }

    if (!blogRecordExists($pdo, 'blog_categories', $categoryId)) {
        throw new InvalidArgumentException('Selected blog category does not exist.');
    }

    if ($id > 0 && !blogRecordExists($pdo, 'blog_posts', $id)) {
        sendBlogJson([
            'success' => false,
            'message' => 'Blog post not found.'
        ], 404);
    }

    $images = [];

    foreach (blogImageFields() as $index => $field) {
        $slot = $index + 1;
        $imageValue = blogNormalizeExistingImage($_POST["existing_image_{$slot}"] ?? '');
        $fileField = "blog_image_{$slot}";

        if (blogHasUpload($fileField)) {
            $imageValue = saveBlogImageUpload($fileField, $postTitle, $slot, $uploadedFiles);
        }

        $images[$field] = $imageValue;
    }

    $seoSlug = blogStringOrNull($_POST['seo_slug'] ?? '');

    $data = [
        'blog_category_id' => $categoryId,
        'post_title' => $postTitle,
        'post_excerpt' => blogStringOrNull($_POST['post_excerpt'] ?? ''),
        'post_content' => blogStringOrNull($_POST['post_content'] ?? ''),
        'seo_slug' => $seoSlug ?: blogSlug($postTitle),
        'meta_title' => blogStringOrNull($_POST['meta_title'] ?? ''),
        'meta_description' => blogStringOrNull($_POST['meta_description'] ?? ''),
        'published_at' => blogPublishedAtOrNull($_POST['published_at'] ?? ''),
        'visible' => isset($_POST['visible']) ? 1 : 0,
        'is_featured' => isset($_POST['is_featured']) ? 1 : 0
    ];

    $data = array_merge($data, $images);

    $pdo->beginTransaction();

    if ($id > 0) {
        $assignments = [];

        foreach (array_keys($data) as $column) {
            $assignments[] = "{$column} = :{$column}";
        }

        $sql = "
            UPDATE blog_posts
            SET " . implode(', ', $assignments) . "
            WHERE id = :id
        ";

        $stmt = $pdo->prepare($sql);
        $data['id'] = $id;
        $stmt->execute($data);
        $savedId = $id;
    } else {
        $data['author_user_id'] = getUserId();

        $columns = array_keys($data);
        $placeholders = array_map(fn($column) => ":{$column}", $columns);

        $sql = "
            INSERT INTO blog_posts (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $placeholders) . ")
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);
        $savedId = (int) $pdo->lastInsertId();
    }

    $pdo->commit();

    sendBlogJson([
        'success' => true,
        'id' => $savedId,
        'message' => $id > 0 ? 'Blog post updated.' : 'Blog post added.'
    ]);
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    cleanupUploadedBlogFiles($uploadedFiles);

    $status = $e instanceof InvalidArgumentException ? 422 : 500;

    sendBlogJson([
        'success' => false,
        'message' => $e->getMessage()
    ], $status);
}
