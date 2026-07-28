<?php
// =========================================
// REGISTER CUSTOMER API
// File: /api/registerCustomer.php
// =========================================

header('Content-Type: application/json');

require_once 'db.php';

try {
    // -------------------------
    // Collect POST data
    // -------------------------
    $first_name    = $_POST['first-name'] ?? '';
    $last_name     = $_POST['last-name'] ?? '';
    $birthdate     = trim((string) ($_POST['birthdate'] ?? ''));
    $email         = $_POST['email_address'] ?? '';
    $password      = $_POST['password'] ?? '';
    $confirm       = $_POST['password-confirm'] ?? '';

    $address_1     = $_POST['address_1'] ?? '';
    $address_2     = $_POST['address_2'] ?? '';
    $city          = $_POST['city'] ?? '';
    $state_prov_id = $_POST['state_prov'] ?? '';
    $postal_code   = $_POST['postal_code'] ?? '';
    $country       = $_POST['country'] ?? '';
    $phone         = $_POST['phone'] ?? '';

    // -------------------------
    // Basic validation
    // -------------------------
    if ($password !== $confirm) {
        echo json_encode([
            "success" => false,
            "message" => "Passwords do not match."
        ]);
        exit;
    }

    if (empty($email) || empty($password) || $birthdate === '') {
        echo json_encode([
            "success" => false,
            "message" => "Required fields missing."
        ]);
        exit;
    }

    $birthdate_value = DateTimeImmutable::createFromFormat('!Y-m-d', $birthdate);
    $birthdate_errors = DateTimeImmutable::getLastErrors();
    $today = new DateTimeImmutable('today');

    if (
        $birthdate_value === false
        || ($birthdate_errors !== false
            && ($birthdate_errors['warning_count'] > 0 || $birthdate_errors['error_count'] > 0))
        || $birthdate_value->format('Y-m-d') !== $birthdate
        || $birthdate_value > $today
    ) {
        echo json_encode([
            "success" => false,
            "message" => "Enter a valid birth date."
        ]);
        exit;
    }

    // -------------------------
    // Hash password
    // -------------------------
    $password_hash = password_hash($password, PASSWORD_BCRYPT);

    // -------------------------
    // Create customer account
    // -------------------------
    $stmt = $pdo->prepare("
        INSERT INTO users (
            first_name,
            last_name,
            birthdate,
            email_address,
            password_hash,
            address_1,
            address_2,
            city,
            state_prov_id,
            postal_code,
            country,
            phone,
            user_role_id,
            created_at
        )
        VALUES (
            :first_name,
            :last_name,
            :birthdate,
            :email,
            :password_hash,
            :address_1,
            :address_2,
            :city,
            :state_prov_id,
            :postal_code,
            :country,
            :phone,
            2,
            NOW()
        )
    ");

    $stmt->execute([
        ':first_name'     => $first_name,
        ':last_name'      => $last_name,
        ':birthdate'      => $birthdate,
        ':email'          => $email,
        ':password_hash'  => $password_hash,
        ':address_1'      => $address_1,
        ':address_2'      => $address_2,
        ':city'           => $city,
        ':state_prov_id'  => $state_prov_id,
        ':postal_code'    => $postal_code,
        ':country'        => $country,
        ':phone'          => $phone
    ]);

    header("Location: /registrationsuccessful.php");

} catch (Exception $e) {

    header("Location: /registrationfailed.php");
}
