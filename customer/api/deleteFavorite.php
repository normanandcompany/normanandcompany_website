<?php

require_once 'profile_helpers.php';
$userId = requireCustomerProfileJson();
requireCustomerProfileCsrf();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendCustomerProfileJson([
        'success' => false,
        'message' => 'This endpoint accepts POST requests only.'
    ], 405);
}

$favoriteId = trim((string) ($_POST['favorite_id'] ?? ''));

if ($favoriteId === '' || !ctype_digit($favoriteId) || (int) $favoriteId <= 0) {
    sendCustomerProfileJson([
        'success' => false,
        'message' => 'Select a valid favorite to remove.'
    ], 422);
}

try {
    $stmt = $pdo->prepare('CALL sp_delete_customer_favorite(:user_id, :favorite_id)');
    $stmt->execute([
        ':user_id' => $userId,
        ':favorite_id' => (int) $favoriteId
    ]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $stmt->closeCursor();

    sendCustomerProfileJson([
        'success' => true,
        'message' => (int) ($result['deleted'] ?? 0) > 0
            ? 'Favorite removed.'
            : 'That favorite was already removed.'
    ]);
} catch (Throwable $e) {
    error_log('Customer favorite delete failed: ' . $e->getMessage());
    sendCustomerProfileJson([
        'success' => false,
        'message' => 'The favorite could not be removed.'
    ], 500);
}
