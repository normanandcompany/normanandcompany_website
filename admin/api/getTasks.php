<?php

require_once 'task_helpers.php';
requireAdminTaskJson();
require_once 'db.php';

function taskSchemaHasColumn(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name");
    $stmt->execute([
        ':table_name' => $table,
        ':column_name' => $column
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function taskSchemaHasTable(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name");
    $stmt->execute([':table_name' => $table]);

    return (int) $stmt->fetchColumn() > 0;
}

try {
    $status = $_GET['status'] ?? null;
    $userId = $_GET['user_id'] ?? null;
    $params = [];
    $where = [];

    if ($status !== null && $status !== '' && $status !== 'all') {
        $where[] = 't.task_status = :status';
        $params[':status'] = taskAllowedValue(
            $status,
            'Task status',
            ['open', 'in_progress', 'completed', 'canceled'],
            'open'
        );
    }

    if ($userId !== null && $userId !== '' && $userId !== 'all') {
        $parsedUserId = taskIntOrNull((string) $userId, 'User ID');

        if ($parsedUserId === null) {
            throw new InvalidArgumentException('User ID is required.');
        }

        $where[] = 't.user_id = :user_id';
        $params[':user_id'] = $parsedUserId;
    }

    $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
    $hasLeadRelationship = taskSchemaHasColumn($pdo, 'tasks', 'lead_id')
        && taskSchemaHasTable($pdo, 'email_leads');
    $hasOpportunityRelationship = taskSchemaHasColumn($pdo, 'tasks', 'opportunity_id')
        && taskSchemaHasTable($pdo, 'opportunities');
    $leadIdSelect = $hasLeadRelationship ? 't.lead_id' : 'NULL AS lead_id';
    $opportunityIdSelect = $hasOpportunityRelationship ? 't.opportunity_id' : 'NULL AS opportunity_id';
    $leadFields = $hasLeadRelationship
        ? "CONCAT_WS(' ', l.first_name, l.last_name) AS lead_name, l.company AS lead_company, l.email_address AS lead_email"
        : 'NULL AS lead_name, NULL AS lead_company, NULL AS lead_email';
    $opportunityField = $hasOpportunityRelationship
        ? 'o.opportunity_name'
        : 'NULL AS opportunity_name';
    $leadJoin = $hasLeadRelationship
        ? 'LEFT JOIN email_leads l ON l.id = t.lead_id'
        : '';
    $opportunityJoin = $hasOpportunityRelationship
        ? 'LEFT JOIN opportunities o ON o.id = t.opportunity_id'
        : '';

    $sql = "
        SELECT
            t.id,
            t.user_id,
            {$leadIdSelect},
            {$opportunityIdSelect},
            t.title,
            t.description,
            t.task_status,
            t.priority,
            t.due_at,
            t.completed_at,
            t.recurrence_group_id,
            t.recurrence_frequency,
            t.recurrence_interval,
            t.recurrence_count,
            t.recurrence_sequence,
            t.created_at,
            t.updated_at,
            CONCAT(u.first_name, ' ', u.last_name) AS assigned_to,
            u.email_address AS assigned_email,
            {$leadFields},
            {$opportunityField}
        FROM tasks t
        INNER JOIN users u ON u.id = t.user_id
        {$leadJoin}
        {$opportunityJoin}
        {$whereSql}
        ORDER BY
            CASE WHEN t.due_at IS NULL THEN 1 ELSE 0 END,
            t.due_at ASC,
            t.created_at DESC,
            t.id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($tasks as &$task) {
        $task['google_calendar_url'] = taskGoogleCalendarUrl($task);
    }

    unset($task);

    echo json_encode($tasks);
} catch (Throwable $e) {
    $statusCode = $e instanceof InvalidArgumentException ? 422 : 500;

    sendTaskJson([
        'success' => false,
        'message' => $e->getMessage()
    ], $statusCode);
}
