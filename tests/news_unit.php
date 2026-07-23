<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/classes/News/Support.php';
require_once dirname(__DIR__).'/classes/News/UrlGuard.php';
require_once dirname(__DIR__).'/classes/News/AlertToken.php';
$failures=[];
function check(bool $condition,string $message):void{global $failures;if(!$condition)$failures[]=$message;}
check(NewsSupport::slug('New Ship: Summer 2026!')==='new-ship-summer-2026','slug normalization');
check(NewsSupport::cleanText('<script>alert(1)</script><b>Safe</b>')==='Safe','dangerous markup stripping');
check(NewsSupport::normalizeUrl('https://Example.com/story?utm_source=x&id=7&fbclid=y')==='https://example.com/story?id=7','tracking URL normalization');
check(NewsSupport::dateOrNull('not a date')===null,'invalid date handling');
foreach(['file:///etc/passwd','http://localhost/feed','http://127.0.0.1/feed','ftp://example.com/feed'] as $url){try{NewsUrlGuard::assertPublicHttpUrl($url);$failures[]='URL guard accepted '.$url;}catch(InvalidArgumentException){}}
putenv('NEWS_UNSUBSCRIBE_SECRET=unit-test-secret-that-is-longer-than-thirty-two-characters');$token=NewsAlertToken::create(42);check(NewsAlertToken::alertId($token)===42,'signed unsubscribe token');check(NewsAlertToken::alertId($token.'x')===null,'tampered unsubscribe token');
if($failures){fwrite(STDERR,"FAIL\n - ".implode("\n - ",$failures)."\n");exit(1);}fwrite(STDOUT,"News unit checks passed.\n");
