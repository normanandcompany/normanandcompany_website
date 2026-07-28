<?php
require_once 'db.php';

header('Content-Type: application/json');

try {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($id <= 0) {
        echo json_encode(["error" => "Invalid product ID"]);
        exit;
    }

    $stmt = $pdo->prepare("CALL GetProductDetails(:id)");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode($product ?: []);
} catch (Exception $e) {
    error_log('Customer product details failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Product details are temporarily unavailable."]);
}
