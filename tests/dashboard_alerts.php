<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';

$pdo = normanCreateDatabaseConnection('admin');
$adminId = (int) $pdo->query("SELECT u.id FROM users u INNER JOIN user_roles r ON r.id=u.user_role_id WHERE r.role_name='admin' AND u.is_active=1 ORDER BY u.id LIMIT 1")->fetchColumn();

if ($adminId < 1) {
    fwrite(STDERR, "Dashboard alert test requires one active administrator.\n");
    exit(1);
}

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$pdo->beginTransaction();
try {
    $pdo->prepare("INSERT INTO tasks(user_id,title,task_status,priority,due_at) VALUES(:user,'Overdue alert test task','open','urgent',DATE_SUB(NOW(),INTERVAL 1 DAY))")
        ->execute([':user' => $adminId]);
    $taskId = (int) $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO tasks(user_id,title,task_status,priority,due_at) VALUES(:user,'Future alert test task','open','normal',DATE_ADD(NOW(),INTERVAL 1 DAY))")
        ->execute([':user' => $adminId]);
    $futureTaskId = (int) $pdo->lastInsertId();

    $pdo->exec("INSERT INTO norman_contacts(full_name,email_address,subject,message,contact_status,created_at) VALUES('Alert Test','alert-test@example.invalid','Overdue contact test','Rollback-only test','new',DATE_SUB(NOW(),INTERVAL 3 DAY))");
    $contactId = (int) $pdo->lastInsertId();

    $taskAlert = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE id=:id AND task_status IN ('open','in_progress') AND due_at<NOW()");
    $taskAlert->execute([':id' => $taskId]);
    $check((int) $taskAlert->fetchColumn() === 1, 'Overdue open task was not alert eligible.');
    $taskAlert->execute([':id' => $futureTaskId]);
    $check((int) $taskAlert->fetchColumn() === 0, 'Future task was incorrectly alert eligible.');

    $contactAlert = $pdo->prepare("SELECT COUNT(*) FROM norman_contacts WHERE id=:id AND contact_status IN ('new','in_progress') AND created_at<DATE_SUB(NOW(),INTERVAL 48 HOUR)");
    $contactAlert->execute([':id' => $contactId]);
    $check((int) $contactAlert->fetchColumn() === 1, 'Overdue unresolved contact request was not alert eligible.');

    $pdo->prepare("UPDATE norman_contacts SET contact_status='resolved',resolved_at=NOW() WHERE id=:id")->execute([':id' => $contactId]);
    $contactAlert->execute([':id' => $contactId]);
    $check((int) $contactAlert->fetchColumn() === 0, 'Resolved contact request remained alert eligible.');
} finally {
    $pdo->rollBack();
}

if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

echo "Dashboard alert checks passed; all test records rolled back.\n";
