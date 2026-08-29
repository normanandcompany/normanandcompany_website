<?php

declare(strict_types=1);

final class PrintfulConfig
{
    public function __construct(
        public readonly string $token,
        public readonly ?string $storeId,
        public readonly bool $autoConfirm,
        public readonly string $appEnvironment,
        public readonly int $timeoutSeconds,
        public readonly int $quoteTtlSeconds,
        public readonly string $webhookSecret
    ) {
    }

    public static function fromEnvironment(bool $requireToken = true): self
    {
        $token = trim((string) normanEnv('PRINTFUL_API_TOKEN', ''));

        if ($requireToken && $token === '') {
            throw new RuntimeException('Printful is not configured. Add PRINTFUL_API_TOKEN to the external environment file.');
        }

        return new self(
            $token,
            self::nullable(normanEnv('PRINTFUL_STORE_ID')),
            self::boolean(normanEnv('PRINTFUL_AUTO_CONFIRM', 'false')),
            strtolower(trim((string) normanEnv('APP_ENV', 'production'))),
            self::boundedInteger(normanEnv('PRINTFUL_TIMEOUT_SECONDS', '20'), 5, 60, 20),
            self::boundedInteger(normanEnv('PRINTFUL_QUOTE_TTL_SECONDS', '1800'), 300, 3600, 1800),
            trim((string) normanEnv('PRINTFUL_WEBHOOK_SECRET', ''))
        );
    }

    public function isDevelopment(): bool
    {
        return in_array($this->appEnvironment, ['development', 'dev', 'local', 'testing', 'test'], true);
    }

    private static function nullable(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private static function boolean(?string $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    private static function boundedInteger(?string $value, int $minimum, int $maximum, int $default): int
    {
        if (!is_numeric($value)) {
            return $default;
        }

        return max($minimum, min($maximum, (int) $value));
    }
}
