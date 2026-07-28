<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';

function sendProductJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function requireAdminJson(): void
{
    if (!isLoggedIn()) {
        sendProductJson([
            'success' => false,
            'message' => 'You must be logged in to manage products.'
        ], 401);
    }

    if (getUserRole() !== 'admin') {
        sendProductJson([
            'success' => false,
            'message' => 'You do not have access to manage products.'
        ], 403);
    }
}

function productImageFields(): array
{
    return [
        'image_url',
        'image_url2',
        'image_url3',
        'image_url4',
        'image_url5',
        'image_url6',
        'image_url7',
        'image_url8',
        'image_url9',
        'image_url10'
    ];
}

function productStringOrNull(?string $value): ?string
{
    $value = trim((string) $value);

    return $value === '' ? null : $value;
}

function productDecimalOrNull(?string $value): ?string
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    if (!is_numeric($value) || (float) $value < 0) {
        throw new InvalidArgumentException('Cost and price must be positive numbers.');
    }

    return number_format((float) $value, 2, '.', '');
}

function productIntOrNull(?string $value, string $fieldName = 'Value'): ?int
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

function productSlug(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim((string) $slug, '-');

    return $slug === '' ? 'product' : $slug;
}

function productExistingImageName(?string $value): ?string
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    return basename(str_replace("\0", '', $value));
}

function productHasUpload(string $field): bool
{
    return isset($_FILES[$field])
        && is_array($_FILES[$field])
        && ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
}

function productUploadErrorMessage(int $error): string
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

function saveProductImageUpload(string $field, string $productName, int $slot, array &$uploadedFiles): string
{
    $file = $_FILES[$field];
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException(productUploadErrorMessage($error));
    }

    $originalName = (string) ($file['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];

    if (!in_array($extension, $allowedExtensions, true)) {
        throw new RuntimeException('Product images must be JPG, PNG, GIF, WebP, or AVIF files.');
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

    $uploadDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/images/products';

    if (!is_dir($uploadDir)) {
        throw new RuntimeException('The product image folder does not exist.');
    }

    if (!is_writable($uploadDir)) {
        throw new RuntimeException('The product image folder is not writable.');
    }

    $filename = sprintf(
        '%s-%s-%02d-%s.%s',
        productSlug($productName),
        date('YmdHis'),
        $slot,
        bin2hex(random_bytes(3)),
        $extension
    );
    $destination = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('The product image could not be saved.');
    }

    $uploadedFiles[] = $destination;

    return $filename;
}

function cleanupUploadedProductFiles(array $uploadedFiles): void
{
    foreach ($uploadedFiles as $file) {
        if (is_string($file) && str_starts_with($file, rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/images/products/') && is_file($file)) {
            unlink($file);
        }
    }
}
