<?php

header('Content-Type: application/json');

require_once 'db.php';

$stmt = $pdo->prepare("CALL sp_get_all_cruise_lines()");

$stmt->execute();

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($products);

$stmt->closeCursor();
?>