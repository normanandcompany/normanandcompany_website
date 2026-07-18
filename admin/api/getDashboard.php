<?php

header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/db.php';

$stmt = $pdo->prepare("CALL sp_get_dashboard_info()");

$stmt->execute();

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($products);

$stmt->closeCursor();
?>