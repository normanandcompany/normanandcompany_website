<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';

function sendUserJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function requireAdminUserJson(): void
{
    if (!isLoggedIn()) {
        sendUserJson([
            'success' => false,
            'message' => 'You must be logged in to manage users.'
        ], 401);
    }

    if (getUserRole() !== 'admin') {
        sendUserJson([
            'success' => false,
            'message' => 'You do not have access to manage users.'
        ], 403);
    }
}

function userStringOrNull(?string $value): ?string
{
    $value = trim((string) $value);

    return $value === '' ? null : $value;
}

function userRequiredString(?string $value, string $fieldName): string
{
    $value = trim((string) $value);

    if ($value === '') {
        throw new InvalidArgumentException($fieldName . ' is required.');
    }

    return $value;
}

function userIntOrNull(?string $value, string $fieldName = 'Value'): ?int
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

function userRecordExists(PDO $pdo, string $table, int $id): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE id = :id");
    $stmt->execute([':id' => $id]);

    return (int) $stmt->fetchColumn() > 0;
}
