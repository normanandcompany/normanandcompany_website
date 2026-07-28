<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/env.php';

$reviewId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($reviewId === false || $reviewId === null || $reviewId < 1) {
    http_response_code(404);
    exit;
}

try {
    $pdo = normanCreateDatabaseConnection('web');
    $statement = $pdo->prepare("
        SELECT rr.photo_data, rr.photo_mime_type, rr.updated_at
        FROM resort_reviews rr
        INNER JOIN users u ON u.id = rr.user_id
        INNER JOIN resorts r ON r.id = rr.resort_id
        WHERE rr.id = :review_id
          AND rr.is_approved = 1
          AND rr.photo_data IS NOT NULL
          AND rr.photo_mime_type IN ('image/jpeg', 'image/png', 'image/webp', 'image/gif')
          AND COALESCE(u.visible, 1) = 1
          AND COALESCE(u.is_active, 1) = 1
          AND COALESCE(r.visible, 1) = 1
        LIMIT 1
    ");
    $statement->execute([':review_id' => $reviewId]);
    $photo = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$photo) {
        http_response_code(404);
        exit;
    }

    $photoData = $photo['photo_data'];
    $mimeType = (string) $photo['photo_mime_type'];
    $modifiedTimestamp = strtotime((string) $photo['updated_at']) ?: time();

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . strlen($photoData));
    header('Cache-Control: public, max-age=300');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modifiedTimestamp) . ' GMT');
    header('X-Content-Type-Options: nosniff');
    echo $photoData;
} catch (Throwable $e) {
    error_log('Unable to load resort review photo: ' . $e->getMessage());
    http_response_code(404);
}
