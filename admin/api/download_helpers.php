<?php

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';

function sendDownloadJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function requireDownloadAdmin(): void
{
    if (!isLoggedIn()) {
        sendDownloadJson(['success' => false, 'message' => 'Administrator login required.'], 401);
    }

    if (getUserRole() !== 'admin') {
        sendDownloadJson(['success' => false, 'message' => 'Administrator access required.'], 403);
    }
}

function downloadRequiredText(mixed $value, string $field, int $maxLength): string
{
    $value = trim((string) $value);

    if ($value === '') {
        throw new InvalidArgumentException($field . ' is required.');
    }

    if (mb_strlen($value) > $maxLength) {
        throw new InvalidArgumentException($field . ' is too long.');
    }

    return $value;
}

function downloadInt(mixed $value, string $field): int
{
    $value = trim((string) $value);

    if ($value === '' || !ctype_digit($value)) {
        throw new InvalidArgumentException($field . ' must be a whole number.');
    }

    return (int) $value;
}

function downloadSafeFilename(string $filename): string
{
    $filename = trim(basename(str_replace('\\', '/', $filename)));
    $filename = preg_replace('/[^A-Za-z0-9._ -]+/', '-', $filename) ?? '';
    $filename = preg_replace('/\s+/', ' ', $filename) ?? '';
    $filename = trim($filename, ". -\t\n\r\0\x0B");

    if ($filename === '' || mb_strlen($filename) > 255 || str_starts_with($filename, '.')) {
        throw new InvalidArgumentException('Enter a valid filename no longer than 255 characters.');
    }

    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $allowedExtensions = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'csv', 'txt', 'rtf', 'zip', 'jpg', 'jpeg', 'png'
    ];

    if (!in_array($extension, $allowedExtensions, true)) {
        throw new InvalidArgumentException('That file type is not allowed.');
    }

    return $filename;
}

function downloadStorageDirectory(): string
{
    return rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/') . '/customer/downloads';
}
