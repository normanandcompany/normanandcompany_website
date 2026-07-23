<?php
declare(strict_types=1);
require_once dirname(__DIR__,3).'/includes/news/bootstrap.php';require_once dirname(__DIR__,3).'/classes/News/ImageService.php';
$user=newsRequireAdminJson();if($_SERVER['REQUEST_METHOD']!=='POST')newsJson(['success'=>false,'message'=>'POST required.'],405);
if(!NewsSupport::verifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN']??($_POST['csrf_token']??'')))newsJson(['success'=>false,'message'=>'Invalid security token.'],403);
try{$service=new NewsImageService(newsPdo('admin'),dirname(__DIR__,3).'/images/news');$image=$service->store($_FILES['image']??[],$user,(int)($_POST['article_id']??0)?:null,$_POST);newsJson(['success'=>true,'image'=>$image,'message'=>'Image uploaded for approval.']);}catch(Throwable $e){error_log('News upload failed: '.$e->getMessage());newsJson(['success'=>false,'message'=>$e instanceof InvalidArgumentException||$e instanceof RuntimeException?$e->getMessage():'Image upload failed.'],422);}
