<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../classes/Email/EmailHtml.php';
require_once __DIR__ . '/../classes/Email/EmailConfig.php';
require_once __DIR__ . '/../classes/Email/EmailToken.php';
require_once __DIR__ . '/../classes/Email/SmtpMailer.php';
require_once __DIR__ . '/../classes/Email/EmailQueueService.php';

$pdo = normanCreateDatabaseConnection('admin');
$lock = $pdo->query("SELECT GET_LOCK('norman_email_queue', 0)")->fetchColumn();
if ((int) $lock !== 1) {
    fwrite(STDOUT, "Email queue is already running.\n");
    exit(0);
}

try {
    $config = (new EmailConfig($pdo))->delivery();
    $result = (new EmailQueueService($pdo, new SmtpMailer(), $config))->process();
    fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL);
} catch (Throwable $e) {
    error_log('Email queue failed: ' . $e->getMessage());
    fwrite(STDERR, "Email queue failed. Check the server error log.\n");
    exit(1);
} finally {
    $pdo->query("SELECT RELEASE_LOCK('norman_email_queue')");
}
