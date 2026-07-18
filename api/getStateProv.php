<?php
// =========================================
// API: GET STATE/PROVINCE INFO
// File: /api/getStateProv.php
// =========================================

header('Content-Type: application/json');

// Include DB connection (adjust path if needed)
require_once 'db.php';

$stmt = $pdo->prepare("CALL sp_get_state_prov()");

$stmt->execute();

$state_provs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($state_provs);

$stmt->closeCursor();
?>