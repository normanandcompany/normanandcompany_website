<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';

if (!headers_sent()) {
    header('Content-Type: application/json');
}

function sendTaskJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function requireAdminTaskJson(): void
{
    if (!isLoggedIn()) {
        sendTaskJson([
            'success' => false,
            'message' => 'You must be logged in to manage tasks.'
        ], 401);
    }

    if (getUserRole() !== 'admin') {
        sendTaskJson([
            'success' => false,
            'message' => 'You do not have access to manage tasks.'
        ], 403);
    }
}

function taskStringOrNull(?string $value): ?string
{
    $value = trim((string) $value);

    return $value === '' ? null : $value;
}

function taskRequiredString(?string $value, string $fieldName): string
{
    $value = trim((string) $value);

    if ($value === '') {
        throw new InvalidArgumentException($fieldName . ' is required.');
    }

    return $value;
}

function taskIntOrNull(?string $value, string $fieldName = 'Value'): ?int
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    if (!ctype_digit($value)) {
        throw new InvalidArgumentException($fieldName . ' must be a whole number.');
    }

    return (int) $value;
}

function taskDateTimeOrNull(?string $value, string $fieldName = 'Date/time'): ?string
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    $value = str_replace('T', ' ', $value);
    $date = DateTimeImmutable::createFromFormat('Y-m-d H:i', $value)
        ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value);

    if (!$date) {
        throw new InvalidArgumentException($fieldName . ' is invalid.');
    }

    return $date->format('Y-m-d H:i:s');
}

function taskAllowedValue(?string $value, string $fieldName, array $allowed, string $default): string
{
    $value = strtolower(trim((string) $value));

    if ($value === '') {
        return $default;
    }

    if (!in_array($value, $allowed, true)) {
        throw new InvalidArgumentException($fieldName . ' is invalid.');
    }

    return $value;
}

function taskBoundedInt(?string $value, string $fieldName, int $default, int $min, int $max): int
{
    $value = trim((string) $value);

    if ($value === '') {
        return $default;
    }

    if (!ctype_digit($value)) {
        throw new InvalidArgumentException($fieldName . ' must be a whole number.');
    }

    $number = (int) $value;

    if ($number < $min || $number > $max) {
        throw new InvalidArgumentException($fieldName . " must be between {$min} and {$max}.");
    }

    return $number;
}

function taskRecordExists(PDO $pdo, string $table, int $id): bool
{
    $allowedTables = ['tasks', 'users', 'email_leads', 'opportunities'];

    if (!in_array($table, $allowedTables, true)) {
        throw new InvalidArgumentException('Invalid table lookup.');
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE id = :id");
    $stmt->execute([':id' => $id]);

    return (int) $stmt->fetchColumn() > 0;
}

function taskResolveCrmRelationship(PDO $pdo, ?int $leadId, ?int $opportunityId): array
{
    if ($opportunityId !== null) {
        $stmt = $pdo->prepare('SELECT lead_id FROM opportunities WHERE id=:id AND archived_at IS NULL');
        $stmt->execute([':id' => $opportunityId]);
        $opportunityLeadId = $stmt->fetchColumn();
        if ($opportunityLeadId === false) {
            throw new InvalidArgumentException('Choose a valid opportunity.');
        }
        if ($leadId !== null && $leadId !== (int) $opportunityLeadId) {
            throw new InvalidArgumentException('The selected opportunity does not belong to the selected lead.');
        }
        return [(int) $opportunityLeadId, $opportunityId];
    }

    if ($leadId !== null && !taskRecordExists($pdo, 'email_leads', $leadId)) {
        throw new InvalidArgumentException('Choose a valid lead.');
    }

    return [$leadId, null];
}

function taskUserIsAdministrator(PDO $pdo, int $userId): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM users u
        INNER JOIN user_roles ur ON ur.id = u.user_role_id
        WHERE u.id = :id
          AND ur.role_name = 'admin'
    ");
    $stmt->execute([':id' => $userId]);

    return (int) $stmt->fetchColumn() > 0;
}

function taskCreateRecurrenceGroupId(): string
{
    try {
        return bin2hex(random_bytes(16));
    } catch (Throwable $e) {
        return str_replace('.', '', uniqid('task', true));
    }
}

function taskAddRecurrenceInterval(string $dueAt, string $frequency, int $interval, int $offset): string
{
    $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dueAt);

    if (!$date) {
        throw new InvalidArgumentException('Due date is invalid.');
    }

    $steps = $interval * $offset;

    if ($steps <= 0) {
        return $date->format('Y-m-d H:i:s');
    }

    $modifier = match ($frequency) {
        'daily' => "+{$steps} days",
        'weekly' => "+{$steps} weeks",
        'monthly' => "+{$steps} months",
        'yearly' => "+{$steps} years",
        default => '+0 days'
    };

    return $date->modify($modifier)->format('Y-m-d H:i:s');
}

function taskSiteTimezone(): DateTimeZone
{
    $timezone = 'America/Chicago';

    if (function_exists('normanEnv')) {
        $configured = normanEnv('NORMAN_SITE_TIMEZONE');

        if (is_string($configured) && trim($configured) !== '') {
            $timezone = trim($configured);
        }
    }

    try {
        return new DateTimeZone($timezone);
    } catch (Throwable $e) {
        return new DateTimeZone('America/Chicago');
    }
}

function taskGoogleCalendarUrl(array $task): string
{
    $params = [
        'action' => 'TEMPLATE',
        'text' => (string) ($task['title'] ?? 'Task')
    ];

    $details = [];
    $description = trim((string) ($task['description'] ?? ''));

    if ($description !== '') {
        $details[] = $description;
        $details[] = '';
    }

    if (!empty($task['assigned_to'])) {
        $details[] = 'Assigned to: ' . $task['assigned_to'];
    }

    if (!empty($task['priority'])) {
        $details[] = 'Priority: ' . ucfirst((string) $task['priority']);
    }

    if (!empty($task['task_status'])) {
        $details[] = 'Status: ' . ucwords(str_replace('_', ' ', (string) $task['task_status']));
    }

    if (!empty($task['opportunity_name'])) {
        $details[] = 'Opportunity: ' . $task['opportunity_name'];
    } elseif (!empty($task['lead_name']) || !empty($task['lead_company'])) {
        $details[] = 'Lead: ' . trim((string) ($task['lead_name'] ?? '') . (!empty($task['lead_company']) ? ' — ' . $task['lead_company'] : ''));
    }

    if (($task['recurrence_frequency'] ?? 'none') !== 'none' && (int) ($task['recurrence_count'] ?? 1) > 1) {
        $details[] = sprintf(
            'Recurring: %s %s of %s',
            ucwords(str_replace('_', ' ', (string) $task['recurrence_frequency'])),
            (string) ($task['recurrence_sequence'] ?? '1'),
            (string) ($task['recurrence_count'] ?? '1')
        );
    }

    if (!empty($task['id'])) {
        $details[] = 'Task ID: ' . $task['id'];
    }

    if ($details !== []) {
        $params['details'] = implode("\n", $details);
    }

    $dueAt = trim((string) ($task['due_at'] ?? ''));

    if ($dueAt !== '') {
        $timezone = taskSiteTimezone();
        $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dueAt, $timezone)
            ?: new DateTimeImmutable($dueAt, $timezone);
        $end = $start->modify('+30 minutes');

        $params['dates'] = $start->format('Ymd\THis') . '/' . $end->format('Ymd\THis');
        $params['ctz'] = $timezone->getName();
    }

    return 'https://calendar.google.com/calendar/render?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}
