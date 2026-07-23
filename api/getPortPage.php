<?php

header('Content-Type: application/json');

require_once 'db.php';

$portId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$portId || $portId < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'A valid port ID is required.']);
    exit;
}

try {
    $stmt = $pdo->prepare('CALL sp_get_port_page(:port_id)');
    $stmt->execute([':port_id' => $portId]);

    $port = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    $stmt->closeCursor();

    if ($port === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Port not found.']);
        exit;
    }

    echo json_encode(['port' => $port]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load port information.']);
}
