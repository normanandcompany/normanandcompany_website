<?php

declare(strict_types=1);

require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/classes/Email/EmailToken.php';

$success = false;
$message = 'This unsubscribe link is invalid.';
$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));

if ($token !== '') {
    try {
        $parsed = EmailToken::parse($token);
        if ($parsed) {
            $pdo = normanCreateDatabaseConnection('web');
            $table = $parsed['type'] === 'newsletter' ? 'email_newsletter_recipients' : 'email_sales_recipients';
            $stmt = $pdo->prepare("SELECT id,email_address FROM {$table} WHERE id=:id AND unsubscribe_token_hash=:hash LIMIT 1");
            $stmt->execute([':id' => $parsed['recipient_id'], ':hash' => hash('sha256', $token)]);
            $recipient = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($recipient && hash_equals($parsed['email_hash'], substr(hash('sha256', strtolower($recipient['email_address'])), 0, 24))) {
                $pdo->beginTransaction();
                $pdo->prepare("INSERT INTO email_suppressions(email_address,reason,source) VALUES(:email,'unsubscribe',:source)
                    ON DUPLICATE KEY UPDATE reason='unsubscribe',source=VALUES(source)")
                    ->execute([':email' => strtolower($recipient['email_address']), ':source' => $parsed['type']]);
                $pdo->prepare("UPDATE {$table} SET status='unsubscribed' WHERE id=:id AND status<>'sent'")->execute([':id' => $recipient['id']]);
                if ($parsed['type'] === 'sales') {
                    $pdo->prepare("UPDATE email_leads SET status='unsubscribed' WHERE email_address=:email")
                        ->execute([':email' => strtolower($recipient['email_address'])]);
                }
                $pdo->commit();
                $success = true;
                $message = 'You have been unsubscribed from Norman and Company email messages.';
            }
        }
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Email unsubscribe failed: ' . $e->getMessage());
        $message = 'We could not process this request right now. Please try again later.';
    }
}

$pageTitle = 'Email Preferences | Norman and Company';
$pageDescription = 'Manage Norman and Company email preferences.';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/navbar.php';
?>
<main class="email-unsubscribe-page">
    <section class="email-unsubscribe-card">
        <p class="admin-eyebrow">Email preferences</p>
        <h1><?= $success ? 'You are unsubscribed' : 'Unable to unsubscribe' ?></h1>
        <p><?= htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
        <a class="btn-primary" href="/">Return to Norman and Company</a>
    </section>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
