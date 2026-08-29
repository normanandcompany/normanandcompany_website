<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/env.php';

$pdo = normanCreateDatabaseConnection('admin');
$sortOrders = [
    'Small' => 10,
    'Medium' => 20,
    'Large' => 30,
    'XL' => 40,
    'XXL' => 50,
];

$pdo->beginTransaction();

try {
    $update = $pdo->prepare('UPDATE apparel_sizes SET sort_order = :sort_order WHERE name = :name');
    $exists = $pdo->prepare('SELECT COUNT(*) FROM apparel_sizes WHERE name = :name');

    foreach ($sortOrders as $name => $sortOrder) {
        $exists->execute([':name' => $name]);
        if ((int) $exists->fetchColumn() !== 1) {
            throw new RuntimeException("Expected exactly one adult apparel size named {$name}.");
        }

        $update->execute([
            ':name' => $name,
            ':sort_order' => $sortOrder,
        ]);
    }

    $pdo->commit();
    echo "Adult apparel size sorting updated.\n";
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $exception;
}
