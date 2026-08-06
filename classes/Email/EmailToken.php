<?php

declare(strict_types=1);

final class EmailToken
{
    public static function make(string $type, int $recipientId, string $email): string
    {
        $emailHash = substr(hash('sha256', strtolower(trim($email))), 0, 24);
        $payload = $type . '|' . $recipientId . '|' . $emailHash;
        $signature = hash_hmac('sha256', $payload, normanRequiredEnv('NORMAN_EMAIL_TOKEN_KEY'));
        return self::encode($payload . '|' . $signature);
    }

    public static function parse(string $token): ?array
    {
        $decoded = self::decode($token);
        if ($decoded === null) {
            return null;
        }
        $parts = explode('|', $decoded);
        if (count($parts) !== 4 || !in_array($parts[0], ['newsletter', 'sales'], true)) {
            return null;
        }
        [$type, $id, $emailHash, $signature] = $parts;
        if (!ctype_digit($id) || (int) $id < 1 || !preg_match('/^[a-f0-9]{24}$/', $emailHash)) {
            return null;
        }
        $payload = $type . '|' . $id . '|' . $emailHash;
        $expected = hash_hmac('sha256', $payload, normanRequiredEnv('NORMAN_EMAIL_TOKEN_KEY'));
        if (!hash_equals($expected, $signature)) {
            return null;
        }
        return ['type' => $type, 'recipient_id' => (int) $id, 'email_hash' => $emailHash];
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function decode(string $value): ?string
    {
        if ($value === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
            return null;
        }
        $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
        return $decoded === false ? null : $decoded;
    }
}
