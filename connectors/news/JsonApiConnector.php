<?php
declare(strict_types=1);
require_once __DIR__ . '/ConnectorInterface.php';
require_once __DIR__ . '/FeedConnector.php';

final class JsonApiConnector implements NewsConnectorInterface
{
    public function fetch(array $source): array
    {
        NewsUrlGuard::assertPublicHttpUrl((string) $source['feed_url']);
        $config = json_decode((string) ($source['connector_config_json'] ?? '{}'), true) ?: [];
        $context = stream_context_create(['http' => ['timeout' => 12, 'user_agent' => 'NormanAndCompanyNewsAggregator/1.0',
            'header' => isset($config['bearer_token_env']) && getenv($config['bearer_token_env']) ? 'Authorization: Bearer ' . getenv($config['bearer_token_env']) : '']]);
        $body = @file_get_contents((string) $source['feed_url'], false, $context);
        $data = is_string($body) ? json_decode($body, true) : null;
        $rows = is_array($data) ? ($data[$config['items_key'] ?? 'articles'] ?? $data) : null;
        if (!is_array($rows)) throw new RuntimeException('The API returned invalid JSON.');
        $map = $config['field_map'] ?? [];
        $result = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $get = static fn(string $name) => $row[$map[$name] ?? $name] ?? null;
            $title = NewsSupport::cleanText((string) $get('headline'), 500); $url = (string) $get('source_url');
            if ($title === '' || !filter_var($url, FILTER_VALIDATE_URL)) continue;
            $result[] = ['external_id' => $get('external_id'), 'headline' => $title, 'summary' => NewsSupport::excerpt((string) $get('summary'), 1000),
                'source_url' => $url, 'canonical_url' => NewsSupport::normalizeUrl($url), 'author' => NewsSupport::cleanText((string) $get('author'), 255) ?: null,
                'published_at' => NewsSupport::dateOrNull((string) $get('published_at')), 'image_url' => $get('image_url'), 'categories' => [], 'keywords' => []];
        }
        return $result;
    }
}
