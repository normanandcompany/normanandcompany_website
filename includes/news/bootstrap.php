<?php
declare(strict_types=1);
$newsRoot = dirname(__DIR__, 2);
require_once $newsRoot . '/api/auth.php';
require_once $newsRoot . '/config/env.php';
require_once $newsRoot . '/classes/News/Support.php';
require_once $newsRoot . '/classes/News/ArticleRepository.php';

function newsPdo(string $role = 'web'): PDO { return normanCreateDatabaseConnection($role); }
function newsEscape(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function newsJson(array $payload, int $status = 200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode($payload, JSON_UNESCAPED_SLASHES); exit; }
function newsRequireAdminJson(): int { if(!isLoggedIn())newsJson(['success'=>false,'message'=>'Authentication required.'],401); if(getUserRole()!=='admin')newsJson(['success'=>false,'message'=>'Administrator access required.'],403); return (int)getUserId(); }
function newsRequireCustomerJson(): int { if(!isLoggedIn())newsJson(['success'=>false,'message'=>'Authentication required.'],401); if(!in_array(getUserRole(),['customer','admin'],true))newsJson(['success'=>false,'message'=>'Customer access required.'],403); return (int)getUserId(); }
function newsRequestData(): array { $type=$_SERVER['CONTENT_TYPE']??''; if(str_contains($type,'application/json')) return json_decode((string)file_get_contents('php://input'),true)?:[]; return $_POST; }
function newsRateLimit(string $key,int $maximum,int $windowSeconds): void { $now=time();$bucket=$_SESSION['news_rate_limits'][$key]??['start'=>$now,'count'=>0];if($now-(int)$bucket['start']>=$windowSeconds)$bucket=['start'=>$now,'count'=>0];$bucket['count']++;$_SESSION['news_rate_limits'][$key]=$bucket;if($bucket['count']>$maximum)newsJson(['success'=>false,'message'=>'Too many requests. Please wait and try again.'],429); }
