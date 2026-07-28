<?php
// =========================================
// API: Send Contact Form
// File: /api/sendContact.php
// =========================================

header('Content-Type: application/json');

// Include DB connection (adjust path if needed)
require_once 'db.php';

try {

    // Read POST data safely
    $fullName = $_POST['fullName'] ?? null;
    $email    = $_POST['email'] ?? null;
    $phone    = $_POST['phone'] ?? null;
    $subject  = $_POST['subject'] ?? null;
    $message  = $_POST['message'] ?? null;

    // Basic validation
    if (!$fullName || !$email || !$subject || !$message) {
        echo json_encode([
            "success" => false,
            "message" => "Missing required fields."
        ]);
        exit;
    }

    // Prepare stored procedure call
    $stmt = $pdo->prepare("CALL sp_insert_contact(?, ?, ?, ?, ?)");

    $stmt->execute([
        $fullName,
        $email,
        $phone,
        $subject,
        $message
    ]);

    $stmt->closeCursor();

    echo json_encode([
        "success" => true,
        "message" => "Message sent successfully!"
    ]);

} catch (Exception $e) {
    error_log('Contact request failed: ' . $e->getMessage());
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Your message could not be sent right now."
    ]);
}
