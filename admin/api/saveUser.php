<?php

require_once 'user_helpers.php';
requireAdminUserJson();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendUserJson([
        'success' => false,
        'message' => 'Users can only be saved with POST.'
    ], 405);
}

try {
    $id = userIntOrNull($_POST['id'] ?? '', 'User ID') ?? 0;
    $firstName = userRequiredString($_POST['first_name'] ?? '', 'First name');
    $lastName = userRequiredString($_POST['last_name'] ?? '', 'Last name');
    $email = userRequiredString($_POST['email_address'] ?? '', 'Email');
    $roleId = userIntOrNull($_POST['user_role_id'] ?? '', 'User role');
    $stateProvId = userIntOrNull($_POST['state_prov_id'] ?? '', 'State/province');
    $birthdate = userRequiredString($_POST['birthdate'] ?? '', 'Birth date');
    $password = (string) ($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Enter a valid email address.');
    }

    $birthdateValue = DateTimeImmutable::createFromFormat('!Y-m-d', $birthdate);
    $birthdateErrors = DateTimeImmutable::getLastErrors();

    if (
        $birthdateValue === false
        || ($birthdateErrors !== false && ($birthdateErrors['warning_count'] > 0 || $birthdateErrors['error_count'] > 0))
        || $birthdateValue->format('Y-m-d') !== $birthdate
    ) {
        throw new InvalidArgumentException('Enter a valid birth date.');
    }

    if ($roleId === null) {
        throw new InvalidArgumentException('User role is required.');
    }

    if ($stateProvId === null) {
        throw new InvalidArgumentException('State/province is required.');
    }

    if (!userRecordExists($pdo, 'user_roles', $roleId)) {
        throw new InvalidArgumentException('Selected user role does not exist.');
    }

    if (!userRecordExists($pdo, 'state_prov', $stateProvId)) {
        throw new InvalidArgumentException('Selected state/province does not exist.');
    }

    if ($id > 0) {
        if (!userRecordExists($pdo, 'users', $id)) {
            sendUserJson([
                'success' => false,
                'message' => 'User not found.'
            ], 404);
        }
    } elseif (trim($password) === '') {
        throw new InvalidArgumentException('Password is required for new users.');
    }

    if (trim($password) !== '' && strlen($password) < 8) {
        throw new InvalidArgumentException('Password must be at least 8 characters.');
    }

    $emailSql = "
        SELECT COUNT(*)
        FROM users
        WHERE email_address = :email
    ";
    $emailParams = [
        ':email' => $email
    ];

    if ($id > 0) {
        $emailSql .= " AND id <> :id";
        $emailParams[':id'] = $id;
    }

    $emailCheck = $pdo->prepare($emailSql);
    $emailCheck->execute($emailParams);

    if ((int) $emailCheck->fetchColumn() > 0) {
        throw new InvalidArgumentException('A user with that email address already exists.');
    }

    $data = [
        'user_role_id' => $roleId,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email_address' => $email,
        'phone' => userStringOrNull($_POST['phone'] ?? ''),
        'address_1' => userStringOrNull($_POST['address_1'] ?? ''),
        'address_2' => userStringOrNull($_POST['address_2'] ?? ''),
        'city' => userStringOrNull($_POST['city'] ?? ''),
        'state_prov_id' => $stateProvId,
        'postal_code' => userStringOrNull($_POST['postal_code'] ?? ''),
        'country' => userStringOrNull($_POST['country'] ?? ''),
        'birthdate' => $birthdate,
        'sweepstakes_active' => isset($_POST['sweepstakes_active']) ? 1 : 0,
        'visible' => isset($_POST['visible']) ? 1 : 0,
        'is_active' => isset($_POST['is_active']) ? 1 : 0
    ];

    if (trim($password) !== '') {
        $data['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
    }

    if ($id > 0) {
        $assignments = [];

        foreach (array_keys($data) as $column) {
            $assignments[] = "{$column} = :{$column}";
        }

        $sql = "
            UPDATE users
            SET " . implode(', ', $assignments) . "
            WHERE id = :id
        ";

        $stmt = $pdo->prepare($sql);
        $data['id'] = $id;
        $stmt->execute($data);
        $savedId = $id;
    } else {
        $columns = array_keys($data);
        $placeholders = array_map(fn($column) => ":{$column}", $columns);

        $sql = "
            INSERT INTO users (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $placeholders) . ")
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);
        $savedId = (int) $pdo->lastInsertId();
    }

    sendUserJson([
        'success' => true,
        'id' => $savedId,
        'message' => $id > 0 ? 'User updated.' : 'User added.'
    ]);
} catch (Throwable $e) {
    $status = $e instanceof InvalidArgumentException ? 422 : 500;

    sendUserJson([
        'success' => false,
        'message' => $e->getMessage()
    ], $status);
}
