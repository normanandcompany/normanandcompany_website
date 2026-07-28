<?php

$normanEnvPath = dirname(__DIR__, 2) . '/config/env.php';

if (is_file($normanEnvPath)) {
    require_once $normanEnvPath;
}

final class CloudflareAnalyticsConfig
{
    private string $apiToken;
    private string $zoneId;
    private string $siteTimezone;
    private int $maxRangeDays;
    private ?string $cacheDir;
    private int $connectTimeoutSeconds;
    private int $requestTimeoutSeconds;
    private array $warnings;
    private string $documentRoot;

    private function __construct(
        string $apiToken,
        string $zoneId,
        string $siteTimezone,
        int $maxRangeDays,
        ?string $cacheDir,
        int $connectTimeoutSeconds,
        int $requestTimeoutSeconds,
        array $warnings,
        string $documentRoot
    ) {
        $this->apiToken = $apiToken;
        $this->zoneId = $zoneId;
        $this->siteTimezone = $siteTimezone;
        $this->maxRangeDays = $maxRangeDays;
        $this->cacheDir = $cacheDir;
        $this->connectTimeoutSeconds = $connectTimeoutSeconds;
        $this->requestTimeoutSeconds = $requestTimeoutSeconds;
        $this->warnings = $warnings;
        $this->documentRoot = $documentRoot;
    }

    public static function load(): self
    {
        $documentRoot = self::resolveDocumentRoot();
        $warnings = [];
        $secrets = self::loadSecretsFile($documentRoot, $warnings);

        $apiToken = self::envValue('CLOUDFLARE_API_TOKEN');
        $zoneId = self::envValue('CLOUDFLARE_ZONE_ID');

        if ($apiToken === '') {
            $apiToken = self::arrayString($secrets, 'CLOUDFLARE_API_TOKEN');
        }

        if ($zoneId === '') {
            $zoneId = self::arrayString($secrets, 'CLOUDFLARE_ZONE_ID');
        }

        $siteTimezone = self::envValue('NORMAN_SITE_TIMEZONE') ?: 'America/Chicago';
        $maxRangeDays = self::boundedInt(self::envValue('CLOUDFLARE_ANALYTICS_MAX_DAYS'), 93, 1, 366);
        $connectTimeout = self::boundedInt(self::envValue('CLOUDFLARE_ANALYTICS_CONNECT_TIMEOUT'), 5, 1, 30);
        $requestTimeout = self::boundedInt(self::envValue('CLOUDFLARE_ANALYTICS_REQUEST_TIMEOUT'), 20, 5, 60);
        $cacheDir = self::resolveCacheDir($documentRoot, $warnings);

        return new self(
            self::normalizeSecret($apiToken),
            self::normalizeZoneId($zoneId),
            $siteTimezone,
            $maxRangeDays,
            $cacheDir,
            $connectTimeout,
            $requestTimeout,
            $warnings,
            $documentRoot
        );
    }

    public function isConfigured(): bool
    {
        return $this->apiToken !== '' && $this->zoneId !== '';
    }

    public function missingKeys(): array
    {
        $missing = [];

        if ($this->apiToken === '') {
            $missing[] = 'CLOUDFLARE_API_TOKEN';
        }

        if ($this->zoneId === '') {
            $missing[] = 'CLOUDFLARE_ZONE_ID';
        }

        return $missing;
    }

    public function apiToken(): string
    {
        return $this->apiToken;
    }

    public function zoneId(): string
    {
        return $this->zoneId;
    }

    public function zoneHash(): string
    {
        return hash('sha256', $this->zoneId ?: 'unconfigured');
    }

    public function siteTimezone(): string
    {
        return $this->siteTimezone;
    }

    public function maxRangeDays(): int
    {
        return $this->maxRangeDays;
    }

    public function cacheDir(): ?string
    {
        return $this->cacheDir;
    }

    public function connectTimeoutSeconds(): int
    {
        return $this->connectTimeoutSeconds;
    }

    public function requestTimeoutSeconds(): int
    {
        return $this->requestTimeoutSeconds;
    }

    public function warnings(): array
    {
        return $this->warnings;
    }

    public function documentRoot(): string
    {
        return $this->documentRoot;
    }

    private static function envValue(string $key): string
    {
        if (function_exists('normanEnv')) {
            $value = normanEnv($key);

            if ($value !== null) {
                return trim($value);
            }
        }

        foreach ([$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)] as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    private static function loadSecretsFile(string $documentRoot, array &$warnings): array
    {
        $path = self::envValue('CLOUDFLARE_ANALYTICS_SECRETS_FILE');

        if ($path === '') {
            $path = dirname($documentRoot) . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'cloudflare-analytics.php';
        }

        if (!@is_file($path)) {
            return [];
        }

        if (self::pathIsInside($path, $documentRoot)) {
            $warnings[] = 'The Cloudflare fallback secrets file must be outside the public web root.';
            return [];
        }

        $config = include $path;

        if (!is_array($config)) {
            $warnings[] = 'The Cloudflare fallback secrets file must return a PHP array.';
            return [];
        }

        return $config;
    }

    private static function arrayString(array $values, string $key): string
    {
        $value = $values[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    private static function normalizeSecret(string $value): string
    {
        $value = trim($value);

        if ($value === '' || str_starts_with($value, 'replace_with_')) {
            return '';
        }

        return $value;
    }

    private static function normalizeZoneId(string $value): string
    {
        $value = self::normalizeSecret($value);

        if ($value === '') {
            return '';
        }

        if (preg_match('/(?:^|\.)([A-Fa-f0-9]{32})$/', $value, $matches)) {
            return strtolower($matches[1]);
        }

        return $value;
    }

    private static function resolveCacheDir(string $documentRoot, array &$warnings): ?string
    {
        $configured = self::envValue('CLOUDFLARE_ANALYTICS_CACHE_DIR');
        $candidates = [];

        if ($configured !== '') {
            $candidates[] = $configured;
        }

        $candidates[] = dirname($documentRoot) . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'cloudflare-analytics';
        $candidates[] = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'norman-cloudflare-analytics';

        foreach ($candidates as $candidate) {
            if (self::pathIsInside($candidate, $documentRoot)) {
                $warnings[] = 'Cloudflare analytics cache must be outside the public web root.';
                continue;
            }

            if (!@is_dir($candidate) && !@mkdir($candidate, 0700, true)) {
                continue;
            }

            if (@is_dir($candidate) && @is_writable($candidate)) {
                return $candidate;
            }
        }

        $warnings[] = 'Cloudflare analytics filesystem cache is disabled because no protected writable cache directory was available.';

        return null;
    }

    private static function boundedInt(string $value, int $default, int $min, int $max): int
    {
        if ($value === '' || !ctype_digit($value)) {
            return $default;
        }

        return max($min, min($max, (int)$value));
    }

    private static function resolveDocumentRoot(): string
    {
        $root = (string)($_SERVER['DOCUMENT_ROOT'] ?? '');

        if ($root === '') {
            $root = dirname(__DIR__, 2);
        }

        return rtrim($root, DIRECTORY_SEPARATOR);
    }

    private static function pathIsInside(string $path, string $root): bool
    {
        $normalizedPath = @realpath($path) ?: $path;
        $normalizedRoot = @realpath($root) ?: $root;

        $normalizedPath = rtrim(str_replace('\\', '/', $normalizedPath), '/');
        $normalizedRoot = rtrim(str_replace('\\', '/', $normalizedRoot), '/');

        return $normalizedPath === $normalizedRoot || str_starts_with($normalizedPath, $normalizedRoot . '/');
    }
}
