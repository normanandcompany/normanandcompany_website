<?php

header('Content-Type: application/json');

require_once 'db.php';
require_once 'auth.php';

$shipId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($shipId === false || $shipId === null || $shipId < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'A valid ship is required.']);
    exit;
}

try {
    $stmt = $pdo->prepare('CALL sp_get_ship_page(:ship_id)');
    $stmt->execute([':ship_id' => $shipId]);
    $ship = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    $stmt->closeCursor();

    if ($ship === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Ship not found.']);
        exit;
    }

    $reviews = [];

    try {
        $reviewStatement = $pdo->prepare("
            SELECT
                sr.id,
                sr.rating,
                sr.review_title,
                sr.review_text,
                sr.created_at,
                sr.updated_at,
                sr.photo_data IS NOT NULL AS has_photo,
                TRIM(CONCAT(
                    u.first_name,
                    CASE
                        WHEN TRIM(COALESCE(u.last_name, '')) = '' THEN ''
                        ELSE CONCAT(' ', LEFT(TRIM(u.last_name), 1), '.')
                    END
                )) AS reviewer_name
            FROM ship_reviews sr
            INNER JOIN users u ON u.id = sr.user_id
            WHERE sr.ship_id = :ship_id
              AND sr.is_approved = 1
              AND COALESCE(u.visible, 1) = 1
              AND COALESCE(u.is_active, 1) = 1
            ORDER BY sr.created_at DESC, sr.id DESC
        ");
        $reviewStatement->execute([':ship_id' => $shipId]);
        $reviews = $reviewStatement->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Keep ship details available if the migration has not been deployed yet.
        error_log('Unable to load ship reviews: ' . $e->getMessage());
    }

    echo json_encode([
        'ship' => $ship,
        'reviews' => $reviews,
        'can_review' => isLoggedIn() && getUserRole() === 'customer'
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load ship data.']);
}
