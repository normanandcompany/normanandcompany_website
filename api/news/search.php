<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/includes/news/bootstrap.php';
try { newsRateLimit('public_search',60,60); $page=max(1,(int)($_GET['page']??1)); $repo=new NewsArticleRepository(newsPdo()); newsJson(['success'=>true]+$repo->publicList($_GET,$page,min(50,max(1,(int)($_GET['limit']??12))))); }
catch(Throwable $e){ error_log('News search failed: '.$e->getMessage()); newsJson(['success'=>false,'message'=>'News search is temporarily unavailable.'],500); }
