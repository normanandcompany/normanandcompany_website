<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';
requireRole('customer');
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/env.php';

$downloads = [];
$loadError = '';

try {
    $pdo = normanCreateDatabaseConnection('web');
    $stmt = $pdo->prepare("SELECT title, description, filename, download_key
        FROM downloads
        WHERE viewable = 1 AND filename <> ''
        ORDER BY title ASC, id ASC");
    $stmt->execute();
    $downloads = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Customer downloads list failed: ' . $e->getMessage());
    $loadError = 'Downloads are temporarily unavailable. Please try again later.';
}
?>

<section class="downloads-page" aria-labelledby="downloadsPageTitle">
    <div id="page-title-meta" data-title="Norman and Company | Downloads" hidden></div>
    <h1 id="downloadsPageTitle">Downloads</h1>
    <p class="downloads-intro">Travel resources available exclusively to Norman and Company members.</p>

    <?php if ($loadError !== ''): ?>
        <p class="downloads-message" role="alert"><?= htmlspecialchars($loadError, ENT_QUOTES, 'UTF-8') ?></p>
    <?php elseif ($downloads === []): ?>
        <p class="downloads-message">There are no downloads available right now.</p>
    <?php else: ?>
        <div class="card-grid downloads-grid">
            <?php foreach ($downloads as $download): ?>
                <?php $url = '/customer/download.php?key=' . rawurlencode((string) $download['download_key']); ?>
                <a class="card download-card" href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>">
                    <span class="download-card__type"><?= htmlspecialchars(strtoupper(pathinfo((string) $download['filename'], PATHINFO_EXTENSION) ?: 'FILE'), ENT_QUOTES, 'UTF-8') ?></span>
                    <h2><?= htmlspecialchars((string) $download['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <p><?= nl2br(htmlspecialchars((string) $download['description'], ENT_QUOTES, 'UTF-8')) ?></p>
                    <span class="download-card__action">Download <?= htmlspecialchars((string) $download['filename'], ENT_QUOTES, 'UTF-8') ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
