<?php

$migrationUser = getenv('NORMAN_DB_MIGRATION_USER');

if ($migrationUser === false || $migrationUser === '') {
    require_once __DIR__ . '/../../admin/api/db.php';
} else {
    $migrationHost = getenv('NORMAN_DB_MIGRATION_HOST') ?: 'localhost';
    $migrationPort = getenv('NORMAN_DB_MIGRATION_PORT') ?: '8889';
    $migrationDb = getenv('NORMAN_DB_MIGRATION_DATABASE') ?: 'normanandcompany';
    $migrationPassword = getenv('NORMAN_DB_MIGRATION_PASSWORD');

    if ($migrationPassword === false) {
        fwrite(STDERR, 'Database password: ');
        $migrationPassword = rtrim((string) fgets(STDIN), "\r\n");
    }

    $pdo = new PDO(
        "mysql:host={$migrationHost};port={$migrationPort};dbname={$migrationDb};charset=utf8mb4",
        $migrationUser,
        $migrationPassword
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}

function taskRecurrenceColumnExists(PDO $pdo, string $column): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'tasks'
          AND COLUMN_NAME = :column
    ");
    $stmt->execute(['column' => $column]);

    return (int) $stmt->fetchColumn() > 0;
}

function taskRecurrenceIndexExists(PDO $pdo, string $index): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'tasks'
          AND INDEX_NAME = :index_name
    ");
    $stmt->execute(['index_name' => $index]);

    return (int) $stmt->fetchColumn() > 0;
}

$columns = [
    'recurrence_group_id' => "VARCHAR(64) NULL DEFAULT NULL AFTER completed_at",
    'recurrence_frequency' => "VARCHAR(20) NOT NULL DEFAULT 'none' AFTER recurrence_group_id",
    'recurrence_interval' => "INT UNSIGNED NOT NULL DEFAULT 1 AFTER recurrence_frequency",
    'recurrence_count' => "INT UNSIGNED NOT NULL DEFAULT 1 AFTER recurrence_interval",
    'recurrence_sequence' => "INT UNSIGNED NOT NULL DEFAULT 1 AFTER recurrence_count"
];

foreach ($columns as $column => $definition) {
    if (!taskRecurrenceColumnExists($pdo, $column)) {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN {$column} {$definition}");
    }
}

if (!taskRecurrenceIndexExists($pdo, 'idx_tasks_recurrence_group')) {
    $pdo->exec("ALTER TABLE tasks ADD INDEX idx_tasks_recurrence_group (recurrence_group_id)");
}

echo "Task recurrence migration complete.\n";
