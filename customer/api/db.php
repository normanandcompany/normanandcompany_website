<?php

require_once __DIR__ . '/../../config/env.php';

try {
    $pdo = normanCreateDatabaseConnection('web');

} catch(Throwable $e) {

    die(json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]));
}
