<?php

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';
requireLogin();
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/env.php';

$downloadKey = trim((string) ($_GET['key'] ?? ''));
$isPreview = (string) ($_GET['preview'] ?? '') === '1';

if (!preg_match('/^[a-zA-Z0-9-]{8,64}$/', $downloadKey)) {
    http_response_code(404);
    exit('Download not found.');
}

try {
    $pdo = normanCreateDatabaseConnection('web');
    $stmt = $pdo->prepare("SELECT id, filename
        FROM downloads
        WHERE download_key = :download_key AND viewable = 1
        LIMIT 1");
    $stmt->execute([':download_key' => $downloadKey]);
    $download = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$download) {
        http_response_code(404);
        exit('Download not found.');
    }

    $filename = basename((string) $download['filename']);
    $filePath = __DIR__ . '/downloads/' . $filename;

    if ($filename === '' || !is_file($filePath) || !is_readable($filePath)) {
        http_response_code(404);
        exit('Download file not found.');
    }

    $mimeType = function_exists('mime_content_type')
        ? (string) mime_content_type($filePath)
        : 'application/octet-stream';
    $mimeType = $mimeType !== '' ? $mimeType : 'application/octet-stream';

    if ($isPreview && !in_array($mimeType, ['image/jpeg', 'image/png', 'application/pdf'], true)) {
        http_response_code(404);
        exit('A preview is not available for this file.');
    }

    if (!$isPreview) {
        $count = $pdo->prepare('UPDATE downloads SET download_count = download_count + 1 WHERE id = :id');
        $count->execute([':id' => (int) $download['id']]);
    }

    $disposition = $isPreview ? 'inline' : 'attachment';
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: ' . $disposition . '; filename="' . addcslashes($filename, "\\\"") . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
    header('Content-Length: ' . (string) filesize($filePath));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store, max-age=0');
    header('Pragma: no-cache');

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    readfile($filePath);
    exit;
} catch (Throwable $e) {
    error_log('Authenticated download failed: ' . $e->getMessage());
    http_response_code(500);
    exit('The download service is temporarily unavailable.');
}
