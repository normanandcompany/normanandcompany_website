<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/api/auth.php';requireRole('admin');require_once dirname(__DIR__).'/config/env.php';require_once dirname(__DIR__).'/classes/News/NewsletterService.php';
try{$service=new NewsNewsletterService(normanCreateDatabaseConnection('admin'));echo $service->render((int)($_GET['id']??0));}catch(Throwable $e){error_log('Newsletter preview failed: '.$e->getMessage());http_response_code(404);echo '<!doctype html><html><body><h1>Newsletter preview unavailable</h1></body></html>';}
