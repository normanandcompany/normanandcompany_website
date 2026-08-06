<?php
declare(strict_types=1);require_once dirname(__DIR__).'/includes/news/bootstrap.php';header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
echo '<url><loc>https://www.normanandcompany.com/customer/news.php</loc></url>' . "\n";
try{$stmt=newsPdo()->query("SELECT slug,updated_at FROM news_articles WHERE status='published' AND published_at<=NOW() ORDER BY updated_at DESC");foreach($stmt as $row)echo '<url><loc>https://www.normanandcompany.com/customer/news.php?article='.rawurlencode($row['slug']).'</loc><lastmod>'.date('c',strtotime($row['updated_at'])).'</lastmod></url>' . "\n";}catch(Throwable $e){error_log('News sitemap failed: '.$e->getMessage());}
echo '</urlset>';
