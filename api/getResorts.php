<?php

header('Content-Type: application/json');

require_once 'db.php';

try {
    $stmt = $pdo->prepare('
        SELECT
            id,
            resort_name,
            city,
            country,
            image_url
        FROM resorts
        WHERE COALESCE(visible, 1) = 1
        ORDER BY resort_name, city, country
    ');
    $stmt->execute();

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load resort data.']);
}
