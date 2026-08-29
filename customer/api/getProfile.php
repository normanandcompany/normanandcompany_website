<?php

require_once 'profile_helpers.php';
$userId = requireCustomerProfileJson();
require_once 'db.php';

try {
    $accountFieldsStmt = $pdo->prepare('
        SELECT
            birthdate,
            sweepstakes_active,
            sweepstakes_won,
            sweepstakes_won_date
        FROM users
        WHERE id = :user_id
          AND COALESCE(is_active, 1) = 1
        LIMIT 1
    ');
    $accountFieldsStmt->execute([':user_id' => $userId]);
    $accountFields = $accountFieldsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

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

    $orderMetaStmt = $pdo->prepare('SELECT id, payment_status, payment_provider, fulfillment_status, currency_code, paid_at FROM orders WHERE user_id = :user_id AND COALESCE(visible, 1) = 1');
    $orderMetaStmt->execute([':user_id' => $userId]);
    $orderMeta = [];
    foreach ($orderMetaStmt->fetchAll(PDO::FETCH_ASSOC) as $meta) {
        $orderMeta[(int) $meta['id']] = $meta;
    }

    $shipmentStmt = $pdo->prepare('SELECT vf.order_id, v.vendor_name, fs.shipment_status, fs.carrier, fs.service, fs.tracking_number, fs.tracking_url, fs.shipped_at, fs.estimated_delivery_at, fs.delivered_at FROM fulfillment_shipments fs INNER JOIN vendor_fulfillments vf ON vf.id = fs.vendor_fulfillment_id INNER JOIN orders o ON o.id = vf.order_id INNER JOIN vendors v ON v.id = vf.vendor_id WHERE o.user_id = :user_id AND COALESCE(o.visible, 1) = 1 ORDER BY fs.shipped_at, fs.id');
    $shipmentStmt->execute([':user_id' => $userId]);
    $shipmentsByOrder = [];
    foreach ($shipmentStmt->fetchAll(PDO::FETCH_ASSOC) as $shipment) {
        $shipmentsByOrder[(int) $shipment['order_id']][] = $shipment;
    }

    if (!$profile) {
        sendCustomerProfileJson([
            'success' => false,
            'message' => 'Your customer profile could not be found.'
        ], 404);
    }

    $profile['birthdate'] = $accountFields['birthdate'] ?? null;
    $profile['sweepstakes_active'] = (int) ($accountFields['sweepstakes_active'] ?? 0);
    $profile['sweepstakes_won'] = (int) ($accountFields['sweepstakes_won'] ?? 0);
    $profile['sweepstakes_won_date'] = $accountFields['sweepstakes_won_date'] ?? null;

    $itemsByOrder = [];

    foreach ($orderItems as $item) {
        $itemsByOrder[(int) $item['order_id']][] = $item;
    }

    foreach ($orders as &$order) {
        $order['items'] = $itemsByOrder[(int) $order['id']] ?? [];
        if (isset($orderMeta[(int) $order['id']])) {
            $order = array_merge($order, $orderMeta[(int) $order['id']]);
        }
        $order['shipments'] = $shipmentsByOrder[(int) $order['id']] ?? [];
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
