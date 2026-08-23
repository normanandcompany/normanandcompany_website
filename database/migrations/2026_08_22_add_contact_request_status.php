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

function contactAlertSchemaExists(PDO $pdo, string $type, string $name): bool
{
    $queries = [
        'column' => "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='norman_contacts' AND COLUMN_NAME=:name",
        'index' => "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='norman_contacts' AND INDEX_NAME=:name"
    ];
    if (!isset($queries[$type])) {
        throw new InvalidArgumentException('Unknown contact alert schema object type.');
    }
    $stmt = $pdo->prepare($queries[$type]);
    $stmt->execute([':name' => $name]);
    return (int) $stmt->fetchColumn() > 0;
}

if (!contactAlertSchemaExists($pdo, 'column', 'contact_status')) {
    $pdo->exec("ALTER TABLE norman_contacts ADD COLUMN contact_status VARCHAR(30) NOT NULL DEFAULT 'new' AFTER message");
}
if (!contactAlertSchemaExists($pdo, 'column', 'resolved_at')) {
    $pdo->exec('ALTER TABLE norman_contacts ADD COLUMN resolved_at DATETIME NULL AFTER contact_status');
}
if (!contactAlertSchemaExists($pdo, 'column', 'updated_at')) {
    $pdo->exec('ALTER TABLE norman_contacts ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER resolved_at');
}
if (!contactAlertSchemaExists($pdo, 'index', 'idx_norman_contacts_status_created')) {
    $pdo->exec('ALTER TABLE norman_contacts ADD INDEX idx_norman_contacts_status_created (contact_status, created_at)');
}

echo "Contact request alert migration complete.\n";
