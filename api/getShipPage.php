<?php

header('Content-Type: application/json');

require_once 'db.php';

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

    echo json_encode(['ship' => $ship]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load ship data.']);
}
