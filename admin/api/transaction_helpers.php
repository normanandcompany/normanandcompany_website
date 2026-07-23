<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';

function sendTransactionJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function requireAdminTransactionJson(): void
{
    if (!isLoggedIn()) {
        sendTransactionJson([
            'success' => false,
            'message' => 'You must be logged in to manage transactions.'
        ], 401);
    }

    if (getUserRole() !== 'admin') {
        sendTransactionJson([
            'success' => false,
            'message' => 'You do not have access to manage transactions.'
        ], 403);
    }
}

function transactionStringOrNull(?string $value, int $maxLength, string $fieldName): ?string
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    if (strlen($value) > $maxLength) {
        throw new InvalidArgumentException($fieldName . ' is too long.');
    }

    return $value;
}

function transactionRequiredString(?string $value, int $maxLength, string $fieldName): string
{
    $value = trim((string) $value);

    if ($value === '') {
        throw new InvalidArgumentException($fieldName . ' is required.');
    }

    if (strlen($value) > $maxLength) {
        throw new InvalidArgumentException($fieldName . ' is too long.');
    }

    return $value;
}

function transactionIntRequired(?string $value, string $fieldName): int
{
    $value = trim((string) $value);

    if ($value === '' || !ctype_digit($value) || (int) $value <= 0) {
        throw new InvalidArgumentException($fieldName . ' must be a positive whole number.');
    }

    return (int) $value;
}

function transactionDecimalOrNull(?string $value, string $fieldName, bool $allowNegative = false): ?string
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    if (!is_numeric($value)) {
        throw new InvalidArgumentException($fieldName . ' must be a number.');
    }

    $number = (float) $value;

    if (!$allowNegative && $number < 0) {
        throw new InvalidArgumentException($fieldName . ' cannot be negative.');
    }

    if (abs($number) > 99999999.99) {
        throw new InvalidArgumentException($fieldName . ' is too large.');
    }

    return number_format($number, 2, '.', '');
}

function transactionDecimalRequired(?string $value, string $fieldName): string
{
    return transactionDecimalOrNull($value === null || trim((string) $value) === '' ? '0' : $value, $fieldName) ?? '0.00';
}

function transactionDateTimeOrNull(?string $value, string $fieldName): ?string
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    $formats = [
        '!Y-m-d\TH:i',
        '!Y-m-d\TH:i:s',
        '!Y-m-d H:i',
        '!Y-m-d H:i:s'
    ];

    foreach ($formats as $format) {
        $date = DateTimeImmutable::createFromFormat($format, $value);

        if ($date instanceof DateTimeImmutable) {
            $errors = DateTimeImmutable::getLastErrors();

            if ($errors === false || ((int) $errors['warning_count'] === 0 && (int) $errors['error_count'] === 0)) {
                return $date->format('Y-m-d H:i:s');
            }
        }
    }

    throw new InvalidArgumentException($fieldName . ' must be a valid date and time.');
}

function transactionRecordExists(PDO $pdo, string $table, int $id): bool
{
    if (!in_array($table, ['orders', 'transactions'], true)) {
        throw new InvalidArgumentException('Invalid table lookup.');
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE id = :id");
    $stmt->execute([':id' => $id]);

    return (int) $stmt->fetchColumn() > 0;
}
