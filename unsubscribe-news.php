<?php
declare(strict_types=1);
require_once __DIR__.'/includes/news/bootstrap.php';require_once __DIR__.'/classes/News/AlertToken.php';$message='This unsubscribe link is invalid or expired.';$token=(string)($_GET['token']??'');
$alertId=NewsAlertToken::alertId($token);if($alertId){try{$pdo=newsPdo('web');$stmt=$pdo->prepare('UPDATE user_news_alerts SET is_active=0,frequency="disabled" WHERE id=:id AND unsubscribe_token_hash=:hash');$stmt->execute([':id'=>$alertId,':hash'=>hash('sha256',$token)]);if($stmt->rowCount())$message='Your Norman and Company news alert has been disabled.';}catch(Throwable $e){error_log('News unsubscribe failed: '.$e->getMessage());}}
include __DIR__.'/includes/header.php';include __DIR__.'/includes/navbar.php';?><main class="news-page"><section class="news-empty"><h1>News alerts</h1><p><?=newsEscape($message)?></p><a class="news-button" href="/news.php">Browse news</a></section></main><?php include __DIR__.'/includes/footer.php';?>
