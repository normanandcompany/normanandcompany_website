<?php
declare(strict_types=1);

final class NewsSupport
{
    public static function slug(string $value, string $fallback = 'news-story'): string
    {
        $value = strtolower(trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-') ?: $fallback;
    }

    public static function cleanText(?string $value, int $maxLength = 20000): string
    {
        $value = preg_replace('#<(script|style|iframe|object|embed)[^>]*>.*?</\1>#is', ' ', (string) $value) ?? '';
        $value = strip_tags($value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';
        return mb_substr(trim($value), 0, $maxLength);
    }

    public static function excerpt(?string $value, int $length = 360): string
    {
        $text = self::cleanText($value);
        if (mb_strlen($text) <= $length) return $text;
        return rtrim(mb_substr($text, 0, $length - 1)) . '…';
    }

    public static function normalizeUrl(string $url): string
    {
        $parts = parse_url(trim($url));
        if (!$parts || empty($parts['host'])) return trim($url);
        $scheme = strtolower($parts['scheme'] ?? 'https');
        $host = strtolower($parts['host']);
        $port = isset($parts['port']) && !in_array((int) $parts['port'], [80, 443], true) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '/';
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
            foreach (['utm_source','utm_medium','utm_campaign','utm_term','utm_content','fbclid','gclid'] as $key) unset($query[$key]);
            ksort($query);
        }
        return $scheme . '://' . $host . $port . $path . ($query ? '?' . http_build_query($query) : '');
    }

    public static function dateOrNull(?string $value): ?string
    {
        if (!$value) return null;
        $time = strtotime($value);
        return $time === false ? null : date('Y-m-d H:i:s', $time);
    }

    public static function uniqueSlug(PDO $pdo, string $headline, ?int $excludeId = null): string
    {
        $base = mb_substr(self::slug($headline), 0, 500);
        $slug = $base;
        for ($i = 2; $i < 10000; $i++) {
            $sql = 'SELECT id FROM news_articles WHERE slug = :slug' . ($excludeId ? ' AND id <> :id' : '') . ' LIMIT 1';
            $stmt = $pdo->prepare($sql);
            $params = [':slug' => $slug];
            if ($excludeId) $params[':id'] = $excludeId;
            $stmt->execute($params);
            if (!$stmt->fetchColumn()) return $slug;
            $slug = mb_substr($base, 0, 490) . '-' . $i;
        }
        return $base . '-' . bin2hex(random_bytes(4));
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['news_csrf'])) $_SESSION['news_csrf'] = bin2hex(random_bytes(32));
        return (string) $_SESSION['news_csrf'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        return is_string($token) && $token !== '' && !empty($_SESSION['news_csrf']) && hash_equals((string) $_SESSION['news_csrf'], $token);
    }
}
