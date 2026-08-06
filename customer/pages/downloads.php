<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';
requireRole('customer');
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/env.php';

$downloads = [];
$downloadCategories = [];
$loadError = '';

try {
    $pdo = normanCreateDatabaseConnection('web');
    $downloadCategories = $pdo->query('SELECT id, description FROM download_category ORDER BY id ASC')
        ->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("SELECT d.title, d.description, d.filename, d.download_key,
            d.download_category_id, dc.description AS category_description
        FROM downloads d
        LEFT JOIN download_category dc ON dc.id = d.download_category_id
        WHERE d.viewable = 1 AND d.filename <> ''
        ORDER BY d.download_category_id IS NULL, d.download_category_id ASC, d.title ASC, d.id ASC");
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
        <div class="downloads-filter">
            <label for="downloadCategoryFilter">Download category</label>
            <select id="downloadCategoryFilter">
                <option value="all">All categories</option>
                <?php foreach ($downloadCategories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>"><?= htmlspecialchars((string) $category['description'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <p class="downloads-message" id="downloadsEmptyFilter" hidden>No downloads are available in this category.</p>
        <div class="card-grid downloads-grid">
            <?php foreach ($downloads as $download): ?>
                <?php
                $url = '/customer/download.php?key=' . rawurlencode((string) $download['download_key']);
                $categoryDescription = trim((string) ($download['category_description'] ?? '')) ?: 'Uncategorized';
                ?>
                <a class="card download-card" data-download-category-id="<?= (int) ($download['download_category_id'] ?? 0) ?>" href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>">
                    <span class="download-card__type"><?= htmlspecialchars(strtoupper(pathinfo((string) $download['filename'], PATHINFO_EXTENSION) ?: 'FILE'), ENT_QUOTES, 'UTF-8') ?></span>
                    <h2><?= htmlspecialchars((string) $download['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <span class="download-card__category"><?= htmlspecialchars($categoryDescription, ENT_QUOTES, 'UTF-8') ?></span>
                    <p><?= nl2br(htmlspecialchars((string) $download['description'], ENT_QUOTES, 'UTF-8')) ?></p>
                    <span class="download-card__action">Download <?= htmlspecialchars((string) $download['filename'], ENT_QUOTES, 'UTF-8') ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
