<?php

require_once 'profile_helpers.php';
$userId = requireCustomerProfileJson();
require_once 'db.php';

try {
    $stmt = $pdo->prepare('CALL sp_get_customer_profile(:user_id)');
    $stmt->execute([':user_id' => $userId]);

    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$stmt->nextRowset()) {
        throw new RuntimeException('The orders result set is missing.');
    }
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$stmt->nextRowset()) {
        throw new RuntimeException('The order items result set is missing.');
    }
    $orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $catalogKeys = [
        'cruise_line',
        'destination',
        'excursion',
        'port',
        'itinerary',
        'ship'
    ];
    $catalogs = [];

    foreach ($catalogKeys as $catalogKey) {
        if (!$stmt->nextRowset()) {
            throw new RuntimeException('A favorites catalog result set is missing.');
        }

        $catalogs[$catalogKey] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if (!$stmt->nextRowset()) {
        throw new RuntimeException('The state and province result set is missing.');
    }
    $states = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$stmt->nextRowset()) {
        throw new RuntimeException('The favorites result set is missing.');
    }
    $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (!$profile) {
        sendCustomerProfileJson([
            'success' => false,
            'message' => 'Your customer profile could not be found.'
        ], 404);
    }

    $itemsByOrder = [];

    foreach ($orderItems as $item) {
        $itemsByOrder[(int) $item['order_id']][] = $item;
    }

    foreach ($orders as &$order) {
        $order['items'] = $itemsByOrder[(int) $order['id']] ?? [];
    }
    unset($order);

    sendCustomerProfileJson([
        'success' => true,
        'csrf_token' => customerProfileCsrfToken(),
        'profile' => $profile,
        'orders' => $orders,
        'favorites' => $favorites,
        'catalogs' => $catalogs,
        'states' => $states
    ]);
} catch (Throwable $e) {
    error_log('Customer profile load failed: ' . $e->getMessage());
    sendCustomerProfileJson([
        'success' => false,
        'message' => 'Your profile information is temporarily unavailable.'
    ], 500);
}
