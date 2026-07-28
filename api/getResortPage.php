<?php

header('Content-Type: application/json');

require_once 'db.php';
require_once 'auth.php';

$resortId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$resortId || $resortId < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'A valid resort ID is required.']);
    exit;
}

try {
    $stmt = $pdo->prepare('CALL sp_get_resort_page(:resort_id)');
    $stmt->execute([':resort_id' => $resortId]);

    $resort = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    $stmt->closeCursor();

    if ($resort === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Resort not found.']);
        exit;
    }

    $reviews = [];

    try {
        $reviewStatement = $pdo->prepare("
            SELECT
                rr.id,
                rr.rating,
                rr.review_title,
                rr.review_text,
                rr.created_at,
                rr.updated_at,
                rr.photo_data IS NOT NULL AS has_photo,
                TRIM(CONCAT(
                    u.first_name,
                    CASE
                        WHEN TRIM(COALESCE(u.last_name, '')) = '' THEN ''
                        ELSE CONCAT(' ', LEFT(TRIM(u.last_name), 1), '.')
                    END
                )) AS reviewer_name
            FROM resort_reviews rr
            INNER JOIN users u ON u.id = rr.user_id
            WHERE rr.resort_id = :resort_id
              AND rr.is_approved = 1
              AND COALESCE(u.visible, 1) = 1
              AND COALESCE(u.is_active, 1) = 1
            ORDER BY rr.created_at DESC, rr.id DESC
        ");
        $reviewStatement->execute([':resort_id' => $resortId]);
        $reviews = $reviewStatement->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Keep resort details available if the migration has not been deployed yet.
        error_log('Unable to load resort reviews: ' . $e->getMessage());
    }

    echo json_encode([
        'resort' => $resort,
        'reviews' => $reviews,
        'can_review' => isLoggedIn() && getUserRole() === 'customer'
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load resort information.']);
}
