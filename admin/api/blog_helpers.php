<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';

function sendBlogJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function requireAdminBlogJson(): void
{
    if (!isLoggedIn()) {
        sendBlogJson([
            'success' => false,
            'message' => 'You must be logged in to manage blog posts.'
        ], 401);
    }

    if (getUserRole() !== 'admin') {
        sendBlogJson([
            'success' => false,
            'message' => 'You do not have access to manage blog posts.'
        ], 403);
    }
}

function blogImageFields(): array
{
    return [
        'featured_image_url',
        'image2_url',
        'image3_url',
        'image4_url',
        'image5_url'
    ];
}

function blogStringOrNull(?string $value): ?string
{
    $value = trim(str_replace("\0", '', (string) $value));

    return $value === '' ? null : $value;
}

function blogRequiredString(?string $value, string $fieldName): string
{
    $value = blogStringOrNull($value);

    if ($value === null) {
        throw new InvalidArgumentException($fieldName . ' is required.');
    }

    return $value;
}

function blogIntOrNull(?string $value, string $fieldName = 'Value'): ?int
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    if (!ctype_digit($value)) {
        throw new InvalidArgumentException($fieldName . ' must be a whole number.');
    }

    return (int) $value;
}

function blogSlug(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim((string) $slug, '-');

    return $slug === '' ? 'blog-post' : $slug;
}

function blogPublishedAtOrNull(?string $value): ?string
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    $timestamp = strtotime(str_replace('T', ' ', $value));

    if ($timestamp === false) {
        throw new InvalidArgumentException('Enter a valid publish date.');
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function blogRecordExists(PDO $pdo, string $table, int $id): bool
{
    $allowedTables = ['blog_posts', 'blog_categories'];

    if (!in_array($table, $allowedTables, true)) {
        throw new InvalidArgumentException('Invalid blog record type.');
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE id = :id");
    $stmt->execute([':id' => $id]);

    return (int) $stmt->fetchColumn() > 0;
}

function blogNormalizeExistingImage(?string $value): ?string
{
    $value = trim(str_replace("\0", '', (string) $value));

    if ($value === '') {
        return null;
    }

    if (preg_match('/^(https?:)?\/\//i', $value)) {
        return $value;
    }

    $value = preg_replace('#^/?images/blog/#i', '', $value);
    $value = preg_replace('#^/?blog/#i', '', (string) $value);
    $filename = basename($value);

    return $filename === '' ? null : '/images/blog/' . $filename;
}

function blogHasUpload(string $field): bool
{
    return isset($_FILES[$field])
        && is_array($_FILES[$field])
        && ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
}

function blogUploadErrorMessage(int $error): string
{
    return match ($error) {
        UPLOAD_ERR_INI_SIZE,
        UPLOAD_ERR_FORM_SIZE => 'The uploaded image is too large.',
        UPLOAD_ERR_PARTIAL => 'The image upload was incomplete.',
        UPLOAD_ERR_NO_TMP_DIR => 'The server is missing an upload temp folder.',
        UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded image.',
        UPLOAD_ERR_EXTENSION => 'A server extension stopped the image upload.',
        default => 'The image could not be uploaded.'
    };
}

function saveBlogImageUpload(string $field, string $postTitle, int $slot, array &$uploadedFiles): string
{
    $file = $_FILES[$field];
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException(blogUploadErrorMessage($error));
    }

    $maxBytes = 10 * 1024 * 1024;

    if ((int) $file['size'] > $maxBytes) {
        throw new RuntimeException('Blog images must be 10 MB or smaller.');
    }

    $originalName = (string) ($file['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];

    if (!in_array($extension, $allowedExtensions, true)) {
        throw new RuntimeException('Blog images must be JPG, PNG, GIF, WebP, or AVIF files.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    $allowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/avif'
    ];

    if (!in_array($mimeType, $allowedMimeTypes, true)) {
        throw new RuntimeException('One of the selected files is not a supported image.');
    }

    $uploadDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/images/blog';

    if (!is_dir($uploadDir)) {
        throw new RuntimeException('The blog image folder does not exist.');
    }

    if (!is_writable($uploadDir)) {
        throw new RuntimeException('The blog image folder is not writable.');
    }

    $filename = sprintf(
        '%s-%s-%02d-%s.%s',
        blogSlug($postTitle),
        date('YmdHis'),
        $slot,
        bin2hex(random_bytes(3)),
        $extension
    );
    $destination = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('The blog image could not be saved.');
    }

    $uploadedFiles[] = $destination;

    return '/images/blog/' . $filename;
}

function cleanupUploadedBlogFiles(array $uploadedFiles): void
{
    $blogImageRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/images/blog/';

    foreach ($uploadedFiles as $file) {
        if (is_string($file) && str_starts_with($file, $blogImageRoot) && is_file($file)) {
            unlink($file);
        }
    }
}
