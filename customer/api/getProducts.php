<?php

header('Content-Type: application/json');

require_once 'db.php';

$product_category_id = $_GET['product_category_id'] ?? null;

if ($product_category_id === '' || $product_category_id === false) {
    $product_category_id = null;
}

$stmt = $pdo->prepare("CALL GetVisibleProducts(?)");

$stmt->bindValue(1, $product_category_id, 
    $product_category_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT
);

$stmt->execute();

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($products);

$stmt->closeCursor();
?>