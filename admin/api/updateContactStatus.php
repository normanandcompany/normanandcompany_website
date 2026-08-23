<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Contact requests can only be updated with POST.']);
    exit;
}

try {
    $payload = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $id = $payload['id'] ?? null;
    $status = strtolower(trim((string) ($payload['contact_status'] ?? '')));
    $allowed = ['new', 'in_progress', 'resolved'];

    if (!ctype_digit((string) $id) || (int) $id < 1) {
        throw new InvalidArgumentException('Choose a valid contact request.');
    }
    if (!in_array($status, $allowed, true)) {
        throw new InvalidArgumentException('Choose a valid contact request status.');
    }

    $stmt = $pdo->prepare("UPDATE norman_contacts SET contact_status=:status,resolved_at=" . ($status === 'resolved' ? 'NOW()' : 'NULL') . ' WHERE id=:id');
    $stmt->execute([':status' => $status, ':id' => (int) $id]);

    if ($stmt->rowCount() < 1) {
        $exists = $pdo->prepare('SELECT COUNT(*) FROM norman_contacts WHERE id=:id');
        $exists->execute([':id' => (int) $id]);
        if ((int) $exists->fetchColumn() < 1) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Contact request not found.']);
            exit;
        }
    }

    echo json_encode(['success' => true, 'message' => 'Contact request updated.']);
} catch (Throwable $e) {
    http_response_code($e instanceof InvalidArgumentException ? 422 : 500);
    echo json_encode([
        'success' => false,
        'message' => $e instanceof InvalidArgumentException ? $e->getMessage() : 'The contact request could not be updated.'
    ]);
}
