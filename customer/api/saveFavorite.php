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

try {
    $type = trim((string) ($_POST['favorite_type'] ?? ''));
    $entityId = trim((string) ($_POST['entity_id'] ?? ''));
    customerFavoriteType($type);

    if ($entityId === '' || !ctype_digit($entityId) || (int) $entityId <= 0) {
        throw new InvalidArgumentException('Select an item to add to your favorites.');
    }

    $stmt = $pdo->prepare('CALL sp_add_customer_favorite(:user_id, :favorite_type, :entity_id)');
    $stmt->execute([
        ':user_id' => $userId,
        ':favorite_type' => $type,
        ':entity_id' => (int) $entityId
    ]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $stmt->closeCursor();

    sendCustomerProfileJson([
        'success' => true,
        'message' => (int) ($result['added'] ?? 0) > 0
            ? 'Favorite added.'
            : 'That item is already in your favorites.'
    ]);
} catch (InvalidArgumentException $e) {
    sendCustomerProfileJson([
        'success' => false,
        'message' => $e->getMessage()
    ], 422);
} catch (PDOException $e) {
    error_log('Customer favorite procedure failed: ' . $e->getMessage());
    sendCustomerProfileJson([
        'success' => false,
        'message' => 'That favorite is unavailable or could not be saved.'
    ], 422);
} catch (Throwable $e) {
    error_log('Customer favorite save failed: ' . $e->getMessage());
    sendCustomerProfileJson([
        'success' => false,
        'message' => 'The favorite could not be saved.'
    ], 500);
}
