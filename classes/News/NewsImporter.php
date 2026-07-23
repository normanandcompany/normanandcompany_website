<?php
declare(strict_types=1);
require_once __DIR__ . '/Support.php';
require_once __DIR__ . '/DuplicateDetector.php';
require_once __DIR__ . '/ClassificationService.php';
require_once __DIR__ . '/RelevanceService.php';
require_once __DIR__ . '/../../connectors/news/FeedConnector.php';
require_once __DIR__ . '/../../connectors/news/JsonApiConnector.php';

final class NewsImporter
{
    private DuplicateDetector $duplicates; private ClassificationService $classifier; private RelevanceService $relevance;
    public function __construct(private PDO $pdo) { $this->duplicates = new DuplicateDetector($pdo); $this->classifier = new ClassificationService($pdo); $this->relevance = new RelevanceService(); }

    public function importSource(int $sourceId, string $trigger = 'manual', ?int $userId = null, bool $preview = false): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM news_sources WHERE id=:id'); $stmt->execute([':id'=>$sourceId]); $source = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$source) throw new InvalidArgumentException('News source not found.');
        $connector = $source['source_type'] === 'api' ? new JsonApiConnector() : new FeedConnector();
        if ($preview) return array_slice(
            $this->filterSourceItems($connector->fetch($source), $source),
            0,
            10
        );
        $log = $this->startLog($sourceId, $trigger, $userId); $counts = ['found'=>0,'imported'=>0,'updated'=>0,'skipped'=>0,'duplicates'=>0,'errors'=>0];
        try {
            $items = $this->filterSourceItems($connector->fetch($source), $source);
            $counts['found'] = count($items);
            foreach ($items as $item) {
                try {
                    $duplicateId = $this->duplicates->exact($item, $sourceId);
                    if ($duplicateId) { $this->pdo->prepare('UPDATE news_articles SET last_seen_at=NOW() WHERE id=:id')->execute([':id'=>$duplicateId]); $counts['duplicates']++; continue; }
                    $this->insert($source, $item); $counts['imported']++;
                } catch (Throwable $e) { $counts['errors']++; error_log('News item import failed: ' . $e->getMessage()); }
            }
            $this->completeLog($log, $counts, $counts['errors'] ? 'partial' : 'success', null);
            $this->pdo->prepare('UPDATE news_sources SET last_fetched_at=NOW(),last_success_at=NOW(),last_error_message=NULL WHERE id=:id')->execute([':id'=>$sourceId]);
            return $counts;
        } catch (Throwable $e) {
            $counts['errors']++; $safe = mb_substr($e->getMessage(), 0, 1000); $this->completeLog($log, $counts, 'failed', $safe);
            $this->pdo->prepare('UPDATE news_sources SET last_fetched_at=NOW(),last_error_at=NOW(),last_error_message=:error WHERE id=:id')->execute([':id'=>$sourceId,':error'=>$safe]); throw $e;
        }
    }

    private function filterSourceItems(array $items, array $source): array
    {
        $config = json_decode((string) ($source['connector_config_json'] ?? ''), true);
        $terms = is_array($config['include_terms'] ?? null)
            ? array_values(array_filter(array_map(
                static fn(mixed $term): string => mb_strtolower(trim((string) $term)),
                $config['include_terms']
            )))
            : [];

        if ($terms === []) return $items;

        return array_values(array_filter($items, static function (array $item) use ($terms): bool {
            $haystack = mb_strtolower(
                (string) ($item['headline'] ?? '') . ' ' . (string) ($item['summary'] ?? '')
            );
            foreach ($terms as $term) {
                if ($term !== '' && str_contains($haystack, $term)) return true;
            }
            return false;
        }));
    }

    private function insert(array $source, array $item): int
    {
        $classification = $this->classifier->classify($item, $source); $score = $this->relevance->score($item, $source, $classification);
        $slug = NewsSupport::uniqueSlug($this->pdo, $item['headline']); $url = NewsSupport::normalizeUrl($item['source_url']);
        $autoPublish = !empty($source['is_official_source'])
            && array_key_exists('requires_manual_review', $source)
            && (int) $source['requires_manual_review'] === 0
            && $score >= 60;
        $stmt = $this->pdo->prepare('INSERT INTO news_articles(source_id,external_id,news_type,category_id,headline,slug,source_headline,summary,source_excerpt,source_url,canonical_url,source_author,source_published_at,status,review_status,content_hash,normalized_url_hash,relevance_score,image_source_url,published_at) VALUES(:source,:external,:type,:category,:headline,:slug,:source_headline,:summary,:excerpt,:url,:canonical,:author,:source_published,:status,:review_status,:content_hash,:url_hash,:score,:image,IF(:auto_publish=1,NOW(),NULL))');
        $stmt->execute([':source'=>$source['id'],':external'=>$item['external_id'],':type'=>$classification['news_type'],':category'=>$classification['category_id'],':headline'=>$item['headline'],':slug'=>$slug,':source_headline'=>$item['headline'],':summary'=>NewsSupport::excerpt($item['summary'],600),':excerpt'=>NewsSupport::excerpt($item['summary'],1000),':url'=>$item['source_url'],':canonical'=>$url,':author'=>$item['author'],':source_published'=>$item['published_at'],':status'=>$autoPublish?'published':'pending_review',':review_status'=>$autoPublish?'auto_published':'unreviewed',':content_hash'=>$this->duplicates->contentHash($item),':url_hash'=>hash('sha256',$url),':score'=>$score,':image'=>$source['image_reuse_allowed'] ? $item['image_url'] : null,':auto_publish'=>$autoPublish?1:0]);
        $id = (int) $this->pdo->lastInsertId();
        try { $this->pdo->prepare('UPDATE news_articles SET suggested_classification_json=:classification,classification_confidence=:confidence WHERE id=:id')->execute([':classification'=>json_encode($classification),':confidence'=>$classification['confidence'],':id'=>$id]); } catch (PDOException) { /* Phase 1 remains independently usable. */ }
        foreach ($this->duplicates->findSimilar($item['headline'], $id) as $match) $this->pdo->prepare('INSERT IGNORE INTO news_article_relations(article_id,related_article_id,similarity_score) VALUES(:article,:related,:score)')->execute([':article'=>$id,':related'=>$match['id'],':score'=>$match['score']]);
        return $id;
    }

    private function startLog(int $source, string $trigger, ?int $user): int { $stmt=$this->pdo->prepare('INSERT INTO news_import_logs(source_id,started_at,status,trigger_type,triggered_by_user_id) VALUES(:source,NOW(),"running",:trigger,:user)'); $stmt->execute([':source'=>$source,':trigger'=>$trigger,':user'=>$user]); return (int)$this->pdo->lastInsertId(); }
    private function completeLog(int $id,array $c,string $status,?string $error): void { $stmt=$this->pdo->prepare('UPDATE news_import_logs SET completed_at=NOW(),status=:status,items_found=:found,items_imported=:imported,items_updated=:updated,items_skipped=:skipped,duplicates_found=:duplicates,errors_found=:errors,error_summary=:error WHERE id=:id'); $stmt->execute([':id'=>$id,':status'=>$status,':found'=>$c['found'],':imported'=>$c['imported'],':updated'=>$c['updated'],':skipped'=>$c['skipped'],':duplicates'=>$c['duplicates'],':errors'=>$c['errors'],':error'=>$error]); }
}
