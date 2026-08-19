<?php

header('Content-Type: application/json');

require_once 'db.php';

try {
    $stmt = $pdo->prepare('
        SELECT
            id,
            destination_name,
            country_name,
            image_url,
            250 AS image_size
        FROM destinations
        WHERE COALESCE(visible, 1) = 1
        ORDER BY destination_name, country_name
    ');
    $stmt->execute();

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load destination data.']);
}
