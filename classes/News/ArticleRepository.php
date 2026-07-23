<?php
declare(strict_types=1);
require_once __DIR__ . '/Support.php';

final class NewsArticleRepository
{
    public function __construct(private PDO $pdo) {}

    public function publicList(array $filters, int $page = 1, int $pageSize = 12): array
    {
        $where = ["a.status='published'", 'a.published_at <= NOW()']; $params = [];
        if (in_array($filters['type'] ?? '', ['cruise','resort'], true)) { $where[]='a.news_type=:type'; $params[':type']=$filters['type']; }
        if (!empty($filters['category'])) { $where[]='c.category_slug=:category'; $params[':category']=$filters['category']; }
        if (!empty($filters['source'])) { $where[]='s.source_slug=:source'; $params[':source']=$filters['source']; }
        if (!empty($filters['entity'])) { $where[]='EXISTS(SELECT 1 FROM news_article_entities ae JOIN news_entities e ON e.id=ae.entity_id WHERE ae.article_id=a.id AND e.entity_slug=:entity)'; $params[':entity']=$filters['entity']; }
        if (!empty($filters['q'])) { $where[]='(a.headline LIKE :q1 OR a.summary LIKE :q2 OR a.editorial_summary LIKE :q3 OR s.source_name LIKE :q4 OR EXISTS(SELECT 1 FROM news_article_keywords ak JOIN news_keywords k ON k.id=ak.keyword_id WHERE ak.article_id=a.id AND k.keyword_name LIKE :q5))'; $query='%'.mb_substr(trim($filters['q']),0,100).'%'; foreach(range(1,5) as $i)$params[':q'.$i]=$query; }
        if (!empty($filters['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)$filters['from'])) { $where[]='COALESCE(a.source_published_at,a.published_at)>=:from'; $params[':from']=$filters['from'].' 00:00:00'; }
        if (!empty($filters['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)$filters['to'])) { $where[]='COALESCE(a.source_published_at,a.published_at)<=:to'; $params[':to']=$filters['to'].' 23:59:59'; }
        $base=' FROM news_articles a LEFT JOIN news_sources s ON s.id=a.source_id LEFT JOIN news_categories c ON c.id=a.category_id WHERE '.implode(' AND ',$where);
        $count=$this->pdo->prepare('SELECT COUNT(*)'.$base); $count->execute($params); $total=(int)$count->fetchColumn();
        $offset=max(0,($page-1)*$pageSize); $sql='SELECT a.*,s.source_name,s.source_slug,c.category_name,c.category_slug'.$base.' ORDER BY a.is_featured DESC,a.featured_rank ASC,COALESCE(a.published_at,a.source_published_at) DESC LIMIT '.(int)$pageSize.' OFFSET '.(int)$offset;
        $stmt=$this->pdo->prepare($sql); $stmt->execute($params);
        return ['articles'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'total'=>$total,'page'=>$page,'pages'=>max(1,(int)ceil($total/$pageSize))];
    }

    public function bySlug(string $slug): ?array
    {
        $stmt=$this->pdo->prepare("SELECT a.*,s.source_name,s.website_url,c.category_name,c.category_slug FROM news_articles a LEFT JOIN news_sources s ON s.id=a.source_id LEFT JOIN news_categories c ON c.id=a.category_id WHERE a.slug=:slug AND a.status='published' AND a.published_at<=NOW() LIMIT 1");
        $stmt->execute([':slug'=>$slug]); $article=$stmt->fetch(PDO::FETCH_ASSOC); if(!$article)return null;
        $stmt=$this->pdo->prepare('SELECT e.*,ae.relationship_type FROM news_article_entities ae JOIN news_entities e ON e.id=ae.entity_id WHERE ae.article_id=:id ORDER BY ae.relationship_type,e.entity_name'); $stmt->execute([':id'=>$article['id']]); $article['entities']=$stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt=$this->pdo->prepare("SELECT a.slug,a.headline,s.source_name,a.source_published_at FROM news_article_relations r JOIN news_articles a ON a.id=r.related_article_id LEFT JOIN news_sources s ON s.id=a.source_id WHERE r.article_id=:id AND a.status='published' ORDER BY r.similarity_score DESC LIMIT 5"); $stmt->execute([':id'=>$article['id']]); $article['related']=$stmt->fetchAll(PDO::FETCH_ASSOC);
        return $article;
    }
}
