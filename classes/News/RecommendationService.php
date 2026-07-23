<?php
declare(strict_types=1);

final class NewsRecommendationService
{
    public function __construct(private PDO $pdo) {}
    public function feed(int $userId, int $page = 1, int $limit = 12): array
    {
        $settings=$this->pdo->prepare('SELECT personalization_enabled,news_type_preference FROM user_news_settings WHERE user_id=:user');$settings->execute([':user'=>$userId]);$userSettings=$settings->fetch(PDO::FETCH_ASSOC)?:['personalization_enabled'=>1,'news_type_preference'=>'both'];
        $weights=['entity'=>40,'category'=>25,'keyword'=>15,'fresh'=>10,'viewed'=>-15];
        try { foreach($this->pdo->query("SELECT setting_key,setting_value FROM news_settings WHERE setting_key LIKE 'recommend_%'")->fetchAll(PDO::FETCH_KEY_PAIR) as $k=>$v) $weights[str_replace('recommend_','',$k)]=(int)$v; } catch(Throwable) {}
        $offset=max(0,($page-1)*$limit);
        $personalized=(int)$userSettings['personalization_enabled']===1;$type=in_array($userSettings['news_type_preference'],['cruise','resort'],true)?$userSettings['news_type_preference']:null;
        $sql="SELECT DISTINCT a.*,s.source_name,c.category_name,
          a.relevance_score
          + IF(".($personalized?'1':'0')."=1 AND EXISTS(SELECT 1 FROM user_news_preferences p JOIN news_article_entities ae ON ae.entity_id=p.entity_id WHERE p.user_id=:u1 AND p.is_active=1 AND ae.article_id=a.id),{$weights['entity']},0)
          + IF(".($personalized?'1':'0')."=1 AND EXISTS(SELECT 1 FROM user_news_preferences p WHERE p.user_id=:u2 AND p.is_active=1 AND p.category_id=a.category_id),{$weights['category']},0)
          + IF(".($personalized?'1':'0')."=1 AND EXISTS(SELECT 1 FROM user_news_preferences p JOIN news_article_keywords ak ON ak.keyword_id=p.keyword_id WHERE p.user_id=:u3 AND p.is_active=1 AND ak.article_id=a.id),{$weights['keyword']},0)
          + IF(a.published_at>=DATE_SUB(NOW(),INTERVAL 48 HOUR),{$weights['fresh']},0)
          + IF(EXISTS(SELECT 1 FROM user_news_article_views v WHERE v.user_id=:u4 AND v.article_id=a.id),{$weights['viewed']},0) AS recommendation_score,
          CASE WHEN EXISTS(SELECT 1 FROM user_news_preferences p JOIN news_article_entities ae ON ae.entity_id=p.entity_id WHERE p.user_id=:u5 AND p.is_active=1 AND ae.article_id=a.id) THEN 'Recommended because you follow a related travel interest.' WHEN EXISTS(SELECT 1 FROM user_news_preferences p WHERE p.user_id=:u6 AND p.is_active=1 AND p.category_id=a.category_id) THEN CONCAT('Recommended because you follow ',c.category_name,'.') ELSE 'Recommended from the latest travel news.' END AS recommendation_reason
          FROM news_articles a LEFT JOIN news_sources s ON s.id=a.source_id LEFT JOIN news_categories c ON c.id=a.category_id
          WHERE a.status='published' AND a.published_at<=NOW() ".($type?' AND a.news_type='.$this->pdo->quote($type):'')." AND NOT EXISTS(SELECT 1 FROM user_news_hidden_articles h WHERE h.user_id=:u7 AND h.article_id=a.id)
          ORDER BY recommendation_score DESC,a.published_at DESC LIMIT ".(int)$limit.' OFFSET '.(int)$offset;
        $stmt=$this->pdo->prepare($sql); $stmt->execute([':u1'=>$userId,':u2'=>$userId,':u3'=>$userId,':u4'=>$userId,':u5'=>$userId,':u6'=>$userId,':u7'=>$userId]); return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
