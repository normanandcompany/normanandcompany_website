<?php

header('Content-Type: application/json');

require_once 'db.php';

$page = trim((string) ($_GET['page'] ?? ''));

if ($page === '' || strlen($page) > 30 || !preg_match('#^/pages/[a-z0-9]+\.php$#', $page)) {
    http_response_code(400);
    echo json_encode(['error' => 'A valid cruise-line page is required.']);
    exit;
}

try {
    $stmt = $pdo->prepare('CALL sp_get_cruise_line_page(:page)');
    $stmt->execute([':page' => $page]);

    $cruiseLine = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    $ships = [];

    if ($stmt->nextRowset()) {
        $ships = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $stmt->closeCursor();

    if ($cruiseLine === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Cruise line not found.']);
        exit;
    }

    echo json_encode([
        'cruise_line' => $cruiseLine,
        'ships' => $ships
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load cruise-line data.']);
}
