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

function profileRequiredValue(string $field, int $maxLength, string $label): string
{
    $value = trim((string) ($_POST[$field] ?? ''));

    if ($value === '') {
        throw new InvalidArgumentException($label . ' is required.');
    }

    if (strlen($value) > $maxLength) {
        throw new InvalidArgumentException($label . ' is too long.');
    }

    return $value;
}

function profileOptionalValue(string $field, int $maxLength, string $label): string
{
    $value = trim((string) ($_POST[$field] ?? ''));

    if (strlen($value) > $maxLength) {
        throw new InvalidArgumentException($label . ' is too long.');
    }

    return $value;
}

try {
    $firstName = profileRequiredValue('first_name', 100, 'First name');
    $lastName = profileRequiredValue('last_name', 100, 'Last name');
    $email = strtolower(profileRequiredValue('email_address', 255, 'Email address'));
    $stateId = trim((string) ($_POST['state_prov_id'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Enter a valid email address.');
    }

    if ($stateId === '' || !ctype_digit($stateId) || (int) $stateId <= 0) {
        throw new InvalidArgumentException('Select a valid state or province.');
    }

    $stmt = $pdo->prepare('CALL sp_update_customer_profile(
        :user_id,
        :first_name,
        :last_name,
        :email_address,
        :phone,
        :address_1,
        :address_2,
        :city,
        :state_prov_id,
        :postal_code,
        :country
    )');
    $stmt->execute([
        ':user_id' => $userId,
        ':first_name' => $firstName,
        ':last_name' => $lastName,
        ':email_address' => $email,
        ':phone' => profileOptionalValue('phone', 50, 'Phone'),
        ':address_1' => profileOptionalValue('address_1', 255, 'Address'),
        ':address_2' => profileOptionalValue('address_2', 255, 'Address 2'),
        ':city' => profileOptionalValue('city', 100, 'City'),
        ':state_prov_id' => (int) $stateId,
        ':postal_code' => profileOptionalValue('postal_code', 25, 'Postal code'),
        ':country' => profileOptionalValue('country', 100, 'Country')
    ]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $stmt->closeCursor();

    $_SESSION['email_address'] = $result['email_address'] ?? $email;

    sendCustomerProfileJson([
        'success' => true,
        'message' => 'Account details updated.'
    ]);
} catch (InvalidArgumentException $e) {
    sendCustomerProfileJson([
        'success' => false,
        'message' => $e->getMessage()
    ], 422);
} catch (PDOException $e) {
    error_log('Customer profile update procedure failed: ' . $e->getMessage());
    $knownMessages = [
        'That email address is already in use.',
        'Enter a valid email address.',
        'Select a valid state or province.',
        'First and last name are required.',
        'Your customer profile could not be found.'
    ];
    $message = 'Your account details could not be updated.';

    foreach ($knownMessages as $knownMessage) {
        if (str_contains($e->getMessage(), $knownMessage)) {
            $message = $knownMessage;
            break;
        }
    }

    sendCustomerProfileJson([
        'success' => false,
        'message' => $message
    ], 422);
} catch (Throwable $e) {
    error_log('Customer profile update failed: ' . $e->getMessage());
    sendCustomerProfileJson([
        'success' => false,
        'message' => 'Your account details could not be updated.'
    ], 500);
}
