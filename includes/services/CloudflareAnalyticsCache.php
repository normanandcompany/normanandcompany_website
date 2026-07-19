<?php

final class CloudflareAnalyticsCache
{
    private ?string $directory;

    public function __construct(?string $directory)
    {
        $this->directory = $directory;
    }

    public function isEnabled(): bool
    {
        return $this->directory !== null && is_dir($this->directory) && is_writable($this->directory);
    }

    public function readFresh(string $key): ?array
    {
        $entry = $this->readEntry($key);

        if ($entry === null) {
            return null;
        }

        if (($entry['expires_at'] ?? 0) < time()) {
            return null;
        }

        return $entry;
    }

    public function readStale(string $key): ?array
    {
        return $this->readEntry($key);
    }

    public function write(string $key, array $payload, int $ttlSeconds): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $now = time();
        $entry = [
            'created_at' => $now,
            'expires_at' => $now + max(60, $ttlSeconds),
            'payload' => $payload
        ];

        $json = json_encode($entry, JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return false;
        }

        $file = $this->pathForKey($key);
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';

        if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
            return false;
        }

        @chmod($tmp, 0600);

        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            return false;
        }

        return true;
    }

    private function readEntry(string $key): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $file = $this->pathForKey($key);

        if (!is_file($file) || !is_readable($file)) {
            return null;
        }

        $json = @file_get_contents($file);

        if ($json === false || $json === '') {
            return null;
        }

        $entry = json_decode($json, true);

        if (!is_array($entry) || !isset($entry['payload']) || !is_array($entry['payload'])) {
            return null;
        }

        return $entry;
    }

    private function pathForKey(string $key): string
    {
        return rtrim((string)$this->directory, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . hash('sha256', $key)
            . '.json';
    }
}
