<?php

header('Content-Type: application/json');

require_once 'db.php';

$stmt = $pdo->prepare("CALL sp_get_all_destinations()");

$stmt->execute();

$destinations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($destinations);

$stmt->closeCursor();
?>