<?php
declare(strict_types=1);
require_once __DIR__ . '/Support.php';

final class DuplicateDetector
{
    public function __construct(private PDO $pdo) {}

    public function exact(array $item, int $sourceId): ?int
    {
        $urlHash = hash('sha256', NewsSupport::normalizeUrl((string) $item['source_url']));
        $contentHash = $this->contentHash($item);
        $clauses = ['normalized_url_hash = :url_hash', 'content_hash = :content_hash'];
        $params = [':url_hash' => $urlHash, ':content_hash' => $contentHash];
        if (!empty($item['external_id'])) { $clauses[] = '(source_id = :source_id AND external_id = :external_id)'; $params[':source_id'] = $sourceId; $params[':external_id'] = $item['external_id']; }
        $stmt = $this->pdo->prepare('SELECT id FROM news_articles WHERE ' . implode(' OR ', $clauses) . ' ORDER BY id LIMIT 1');
        $stmt->execute($params);
        $id = $stmt->fetchColumn();
        if ($id) return (int) $id;
        $stmt = $this->pdo->prepare('SELECT id, headline FROM news_articles WHERE source_id = :source AND COALESCE(source_published_at,created_at) >= DATE_SUB(NOW(), INTERVAL 14 DAY)');
        $stmt->execute([':source' => $sourceId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) if ($this->similarity((string) $item['headline'], $row['headline']) >= 96) return (int) $row['id'];
        return null;
    }

    public function findSimilar(string $headline, int $excludeId, int $days = 7): array
    {
        $stmt = $this->pdo->prepare("SELECT id,headline FROM news_articles WHERE id <> :id AND COALESCE(source_published_at,created_at) >= DATE_SUB(NOW(), INTERVAL {$days} DAY) ORDER BY id DESC LIMIT 250");
        $stmt->execute([':id' => $excludeId]); $matches = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $score = $this->similarity($headline, $row['headline']);
            if ($score >= 72) $matches[] = ['id' => (int) $row['id'], 'score' => $score];
        }
        return $matches;
    }

    public function contentHash(array $item): string
    {
        return hash('sha256', $this->normalizedText((string) $item['headline']) . '|' . $this->normalizedText((string) ($item['summary'] ?? '')));
    }

    public function similarity(string $left, string $right): float
    {
        $a = array_values(array_unique(explode(' ', $this->normalizedText($left))));
        $b = array_values(array_unique(explode(' ', $this->normalizedText($right))));
        if (!$a || !$b) return 0;
        $jaccard = count(array_intersect($a, $b)) / max(1, count(array_unique(array_merge($a, $b))));
        similar_text($this->normalizedText($left), $this->normalizedText($right), $character);
        return round(($jaccard * 60) + ($character * .4), 2);
    }

    private function normalizedText(string $text): string
    {
        $text = strtolower(NewsSupport::cleanText($text));
        return trim(preg_replace('/[^a-z0-9]+/', ' ', $text) ?? '');
    }
}
