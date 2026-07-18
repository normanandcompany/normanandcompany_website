<?php

header('Content-Type: application/json');

require_once 'db.php';

$stmt = $pdo->prepare("CALL sp_get_apparel_sizes()");

$stmt->execute();

$sizes = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($sizes);

$stmt->closeCursor();
?>