<?php
declare(strict_types=1);
require_once __DIR__.'/Support.php';

final class NewsNewsletterService
{
 public function __construct(private PDO $pdo){}
 public function render(int $newsletterId):string
 {
  $stmt=$this->pdo->prepare('SELECT * FROM news_newsletters WHERE id=:id');$stmt->execute([':id'=>$newsletterId]);$newsletter=$stmt->fetch(PDO::FETCH_ASSOC);if(!$newsletter)throw new InvalidArgumentException('Newsletter not found.');
  $stmt=$this->pdo->prepare("SELECT a.*,na.custom_headline,na.custom_summary,s.source_name FROM news_newsletter_articles na JOIN news_articles a ON a.id=na.article_id LEFT JOIN news_sources s ON s.id=a.source_id WHERE na.newsletter_id=:id AND a.status='published' ORDER BY na.display_order,a.published_at DESC");$stmt->execute([':id'=>$newsletterId]);$articles=$stmt->fetchAll(PDO::FETCH_ASSOC);
  $html='<!doctype html><html><body style="font-family:Arial,sans-serif;color:#1D3557;max-width:680px;margin:auto"><h1>'.htmlspecialchars($newsletter['newsletter_subject'],ENT_QUOTES,'UTF-8').'</h1>';
  foreach($articles as $a)$html.='<article style="border-bottom:1px solid #ddd;padding:16px 0"><h2><a href="https://www.normanandcompany.com/customer/news.php?article='.rawurlencode($a['slug']).'">'.htmlspecialchars($a['custom_headline']?:$a['headline'],ENT_QUOTES,'UTF-8').'</a></h2><p>'.htmlspecialchars($a['custom_summary']?:$a['summary'],ENT_QUOTES,'UTF-8').'</p><small>Source: '.htmlspecialchars($a['source_name']?:'Norman and Company',ENT_QUOTES,'UTF-8').'</small></article>';
  return $html.'<p><a href="https://www.normanandcompany.com/customer/">Manage your news preferences</a></p></body></html>';
 }
}
