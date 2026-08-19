<?php

header('Content-Type: application/json');

require_once 'db.php';

$destinationId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$destinationId || $destinationId < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'A valid destination ID is required.']);
    exit;
}

try {
    $stmt = $pdo->prepare('
        SELECT
            id,
            destination_name,
            country_name,
            description,
            image_url,
            seo_slug,
            meta_title,
            meta_description,
            latitude,
            longitude
        FROM destinations
        WHERE id = :destination_id
          AND COALESCE(visible, 1) = 1
        LIMIT 1
    ');
    $stmt->execute([':destination_id' => $destinationId]);

    $destination = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($destination === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Destination not found.']);
        exit;
    }

    echo json_encode(['destination' => $destination]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load destination information.']);
}
