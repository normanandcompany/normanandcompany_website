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

function taskCrmSchemaObjectExists(PDO $pdo, string $type, string $name): bool
{
    $queries = [
        'column' => "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tasks' AND COLUMN_NAME=:name",
        'index' => "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tasks' AND INDEX_NAME=:name",
        'constraint' => "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='tasks' AND CONSTRAINT_NAME=:name"
    ];
    if (!isset($queries[$type])) {
        throw new InvalidArgumentException('Unknown schema object type.');
    }
    $stmt = $pdo->prepare($queries[$type]);
    $stmt->execute([':name' => $name]);
    return (int) $stmt->fetchColumn() > 0;
}

if (!taskCrmSchemaObjectExists($pdo, 'column', 'lead_id')) {
    $pdo->exec('ALTER TABLE tasks ADD COLUMN lead_id BIGINT UNSIGNED NULL AFTER user_id');
}
if (!taskCrmSchemaObjectExists($pdo, 'column', 'opportunity_id')) {
    $pdo->exec('ALTER TABLE tasks ADD COLUMN opportunity_id BIGINT UNSIGNED NULL AFTER lead_id');
}
if (!taskCrmSchemaObjectExists($pdo, 'index', 'idx_tasks_lead')) {
    $pdo->exec('ALTER TABLE tasks ADD INDEX idx_tasks_lead (lead_id)');
}
if (!taskCrmSchemaObjectExists($pdo, 'index', 'idx_tasks_opportunity')) {
    $pdo->exec('ALTER TABLE tasks ADD INDEX idx_tasks_opportunity (opportunity_id)');
}
if (!taskCrmSchemaObjectExists($pdo, 'constraint', 'fk_tasks_lead')) {
    $pdo->exec('ALTER TABLE tasks ADD CONSTRAINT fk_tasks_lead FOREIGN KEY (lead_id) REFERENCES email_leads(id) ON UPDATE CASCADE ON DELETE SET NULL');
}
if (!taskCrmSchemaObjectExists($pdo, 'constraint', 'fk_tasks_opportunity')) {
    $pdo->exec('ALTER TABLE tasks ADD CONSTRAINT fk_tasks_opportunity FOREIGN KEY (opportunity_id) REFERENCES opportunities(id) ON UPDATE CASCADE ON DELETE SET NULL');
}

echo "Task CRM relationship migration complete.\n";
