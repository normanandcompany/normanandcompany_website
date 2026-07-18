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

    if (empty($email) || empty($password)) {
        echo json_encode([
            "success" => false,
            "message" => "Required fields missing."
        ]);
        exit;
    }

    // -------------------------
    // Hash password
    // -------------------------
    $password_hash = password_hash($password, PASSWORD_BCRYPT);

    // -------------------------
    // Call stored procedure
    // -------------------------
    $stmt = $pdo->prepare("CALL sp_register_customer(
        :first_name,
        :last_name,
        :email,
        :password_hash,
        :address_1,
        :address_2,
        :city,
        :state_prov_id,
        :postal_code,
        :country,
        :phone
    )");

    $stmt->execute([
        ':first_name'     => $first_name,
        ':last_name'      => $last_name,
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

    $stmt->closeCursor();

    header("Location: /registrationsuccessful.php");

} catch (Exception $e) {

    header("Location: /registrationfailed.php");
}