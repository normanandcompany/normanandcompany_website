<?php

require_once 'task_helpers.php';
requireAdminTaskJson();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendTaskJson([
        'success' => false,
        'message' => 'Tasks can only be saved with POST.'
    ], 405);
}

try {
    $id = taskIntOrNull($_POST['id'] ?? '', 'Task ID') ?? 0;
    $userId = taskIntOrNull($_POST['user_id'] ?? '', 'Assigned user');
    $leadId = taskIntOrNull($_POST['lead_id'] ?? '', 'Lead');
    $opportunityId = taskIntOrNull($_POST['opportunity_id'] ?? '', 'Opportunity');
    $title = taskRequiredString($_POST['title'] ?? '', 'Task title');
    $description = taskStringOrNull($_POST['description'] ?? '');
    $status = taskAllowedValue(
        $_POST['task_status'] ?? '',
        'Task status',
        ['open', 'in_progress', 'completed', 'canceled'],
        'open'
    );
    $priority = taskAllowedValue(
        $_POST['priority'] ?? '',
        'Priority',
        ['low', 'normal', 'high', 'urgent'],
        'normal'
    );
    $dueAt = taskDateTimeOrNull($_POST['due_at'] ?? '', 'Due date');
    $isRecurring = $id === 0 && isset($_POST['is_recurring']) && (string) $_POST['is_recurring'] === '1';
    $recurrenceFrequency = 'none';
    $recurrenceInterval = 1;
    $recurrenceCount = 1;
    $existingCompletedAt = null;

    if ($userId === null) {
        throw new InvalidArgumentException('Assigned user is required.');
    }

    if (strlen($title) > 255) {
        throw new InvalidArgumentException('Task title must be 255 characters or fewer.');
    }

    if (!taskUserIsAdministrator($pdo, $userId)) {
        throw new InvalidArgumentException('Tasks can only be assigned to administrators.');
    }

    [$leadId, $opportunityId] = taskResolveCrmRelationship($pdo, $leadId, $opportunityId);

    if ($isRecurring) {
        if ($dueAt === null) {
            throw new InvalidArgumentException('Due date is required for recurring tasks.');
        }

        $recurrenceFrequency = taskAllowedValue(
            $_POST['recurrence_frequency'] ?? '',
            'Recurrence frequency',
            ['daily', 'weekly', 'monthly', 'yearly'],
            'weekly'
        );
        $recurrenceInterval = taskBoundedInt($_POST['recurrence_interval'] ?? '', 'Recurrence interval', 1, 1, 12);
        $recurrenceCount = taskBoundedInt($_POST['recurrence_count'] ?? '', 'Occurrences', 2, 2, 52);
    }

    if ($id > 0) {
        $existing = $pdo->prepare("
            SELECT completed_at
            FROM tasks
            WHERE id = :id
            LIMIT 1
        ");
        $existing->execute([':id' => $id]);
        $existingTask = $existing->fetch(PDO::FETCH_ASSOC);

        if (!$existingTask) {
            sendTaskJson([
                'success' => false,
                'message' => 'Task not found.'
            ], 404);
        }

        $existingCompletedAt = $existingTask['completed_at'] ?? null;
    }

    $completedAt = null;

    if ($status === 'completed') {
        $completedAt = $existingCompletedAt ?: date('Y-m-d H:i:s');
    }

    $data = [
        'user_id' => $userId,
        'lead_id' => $leadId,
        'opportunity_id' => $opportunityId,
        'title' => $title,
        'description' => $description,
        'task_status' => $status,
        'priority' => $priority,
        'due_at' => $dueAt,
        'completed_at' => $completedAt,
        'recurrence_group_id' => null,
        'recurrence_frequency' => 'none',
        'recurrence_interval' => 1,
        'recurrence_count' => 1,
        'recurrence_sequence' => 1
    ];

    if ($id > 0) {
        $sql = "
            UPDATE tasks
            SET
                user_id = :user_id,
                lead_id = :lead_id,
                opportunity_id = :opportunity_id,
                title = :title,
                description = :description,
                task_status = :task_status,
                priority = :priority,
                due_at = :due_at,
                completed_at = :completed_at
            WHERE id = :id
        ";

        $stmt = $pdo->prepare($sql);
        unset(
            $data['recurrence_group_id'],
            $data['recurrence_frequency'],
            $data['recurrence_interval'],
            $data['recurrence_count'],
            $data['recurrence_sequence']
        );
        $data['id'] = $id;
        $stmt->execute($data);
        $savedId = $id;
    } else {
        $sql = "
            INSERT INTO tasks (
                user_id,
                lead_id,
                opportunity_id,
                title,
                description,
                task_status,
                priority,
                due_at,
                completed_at,
                recurrence_group_id,
                recurrence_frequency,
                recurrence_interval,
                recurrence_count,
                recurrence_sequence
            ) VALUES (
                :user_id,
                :lead_id,
                :opportunity_id,
                :title,
                :description,
                :task_status,
                :priority,
                :due_at,
                :completed_at,
                :recurrence_group_id,
                :recurrence_frequency,
                :recurrence_interval,
                :recurrence_count,
                :recurrence_sequence
            )
        ";

        $stmt = $pdo->prepare($sql);
        $savedId = 0;

        if ($isRecurring) {
            $pdo->beginTransaction();
            $recurrenceGroupId = taskCreateRecurrenceGroupId();

            for ($index = 0; $index < $recurrenceCount; $index++) {
                $row = $data;
                $row['due_at'] = taskAddRecurrenceInterval($dueAt, $recurrenceFrequency, $recurrenceInterval, $index);
                $row['recurrence_group_id'] = $recurrenceGroupId;
                $row['recurrence_frequency'] = $recurrenceFrequency;
                $row['recurrence_interval'] = $recurrenceInterval;
                $row['recurrence_count'] = $recurrenceCount;
                $row['recurrence_sequence'] = $index + 1;

                $stmt->execute($row);

                if ($savedId === 0) {
                    $savedId = (int) $pdo->lastInsertId();
                }
            }

            $pdo->commit();
        } else {
            $stmt->execute($data);
            $savedId = (int) $pdo->lastInsertId();
        }
    }

    sendTaskJson([
        'success' => true,
        'id' => $savedId,
        'message' => $id > 0
            ? 'Task updated.'
            : ($isRecurring ? 'Recurring tasks added.' : 'Task added.')
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $statusCode = $e instanceof InvalidArgumentException ? 422 : 500;

    sendTaskJson([
        'success' => false,
        'message' => $e->getMessage()
    ], $statusCode);
}
