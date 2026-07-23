<?php

header('Content-Type: application/json');

require_once 'db.php';

try {
    $stmt = $pdo->prepare('CALL sp_get_port_cards()');
    $stmt->execute();

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    $stmt->closeCursor();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load port data.']);
}
