<?php

require_once 'task_helpers.php';
requireAdminTaskJson();
require_once 'db.php';

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

    $sql = "
        SELECT
            t.id,
            t.user_id,
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
            u.email_address AS assigned_email
        FROM tasks t
        INNER JOIN users u ON u.id = t.user_id
        {$whereSql}
        ORDER BY
            CASE t.task_status
                WHEN 'open' THEN 1
                WHEN 'in_progress' THEN 2
                WHEN 'completed' THEN 3
                WHEN 'canceled' THEN 4
                ELSE 5
            END,
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
