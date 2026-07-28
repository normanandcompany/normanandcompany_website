<?php

require_once 'user_helpers.php';
requireAdminUserJson();

header('Cache-Control: no-store, max-age=0');
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendUserJson([
        'success' => false,
        'message' => 'Book winners can only be chosen with POST.'
    ], 405);
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->query("
        SELECT
            u.id,
            u.first_name,
            u.last_name,
            CONCAT_WS(' ', NULLIF(TRIM(u.first_name), ''), NULLIF(TRIM(u.last_name), '')) AS full_name,
            u.email_address,
            u.phone,
            u.address_1,
            u.address_2,
            u.city,
            sp.name AS state_province,
            u.postal_code,
            u.country,
            u.birthdate,
            TIMESTAMPDIFF(YEAR, u.birthdate, CURDATE()) AS age,
            u.created_at
        FROM users u
        INNER JOIN user_roles ur ON ur.id = u.user_role_id
        LEFT JOIN state_prov sp ON sp.id = u.state_prov_id
        WHERE LOWER(TRIM(ur.role_name)) = 'customer'
          AND COALESCE(u.is_active, 1) = 1
          AND COALESCE(u.sweepstakes_active, 0) = 1
          AND COALESCE(u.sweepstakes_won, 0) = 0
          AND u.birthdate IS NOT NULL
          AND u.birthdate <= DATE_SUB(CURDATE(), INTERVAL 18 YEAR)
        ORDER BY RAND()
        LIMIT 1
        FOR UPDATE
    ");

    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        $pdo->rollBack();
        sendUserJson([
            'success' => false,
            'message' => 'No eligible adult sweepstakes participants are available for selection.'
        ], 404);
    }

    $drawingDate = (string) $pdo->query('SELECT CURDATE()')->fetchColumn();
    $update = $pdo->prepare("
        UPDATE users
        SET sweepstakes_won = 1,
            sweepstakes_won_date = :drawing_date
        WHERE id = :user_id
          AND COALESCE(sweepstakes_won, 0) = 0
    ");
    $update->execute([
        ':drawing_date' => $drawingDate,
        ':user_id' => (int) $customer['id']
    ]);

    if ($update->rowCount() !== 1) {
        throw new RuntimeException('The selected customer could not be recorded as the winner.');
    }

    $pdo->commit();
    $customer['sweepstakes_won'] = 1;
    $customer['sweepstakes_won_date'] = $drawingDate;

    sendUserJson([
        'success' => true,
        'customer' => $customer,
        'message' => 'Winner selected and drawing recorded.'
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Book chooser customer selection failed: ' . $e->getMessage());
    sendUserJson([
        'success' => false,
        'message' => 'Unable to choose a customer right now. Please try again.'
    ], 500);
}
