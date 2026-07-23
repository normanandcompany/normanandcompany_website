<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);require_once $root.'/config/env.php';require_once $root.'/classes/News/NewsImporter.php';
try{$pdo=normanCreateDatabaseConnection('admin');$lock=(int)$pdo->query("SELECT GET_LOCK('norman_news_import',0)")->fetchColumn();if($lock!==1){fwrite(STDERR,"Another news import is running.\n");exit(2);}
 $sourceId=isset($argv[1])?(int)$argv[1]:0;$sql='SELECT id FROM news_sources WHERE is_active=1'.($sourceId?' AND id=:id':' AND (last_fetched_at IS NULL OR last_fetched_at<=DATE_SUB(NOW(),INTERVAL fetch_frequency_minutes MINUTE))').' ORDER BY id';$stmt=$pdo->prepare($sql);$stmt->execute($sourceId?[':id'=>$sourceId]:[]);$importer=new NewsImporter($pdo);$failed=0;
 foreach($stmt->fetchAll(PDO::FETCH_COLUMN) as $id){try{$counts=$importer->importSource((int)$id,'scheduled');fwrite(STDOUT,"Source {$id}: {$counts['imported']} imported, {$counts['duplicates']} duplicates.\n");}catch(Throwable $e){$failed++;fwrite(STDERR,"Source {$id}: import failed.\n");}}
 $pdo->query("SELECT RELEASE_LOCK('norman_news_import')");exit($failed?1:0);
}catch(Throwable $e){fwrite(STDERR,"News importer failed: ".$e->getMessage()."\n");exit(1);}
