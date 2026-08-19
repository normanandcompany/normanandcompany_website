<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/report_catalog.php';

requireRole('admin');

$reports = adminReportCatalog();
?>

<section>
    <div id="page-title-meta"
        data-title="Norman and Company | Reports"
        style="display: none;">
    </div>

    <h1>Reports</h1>
    <p>Download current administrative data as formatted Excel workbooks.</p>

    <div class="card-grid reports-card-grid">
        <?php foreach ($reports as $reportKey => $report): ?>
            <div class="card report-card">
                <h3><?= htmlspecialchars($report['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                <p><?= htmlspecialchars($report['description'], ENT_QUOTES, 'UTF-8') ?></p>
                <p class="report-card-action">
                    <a class="btn-primary"
                        href="/admin/api/reports.php?report=<?= rawurlencode($reportKey) ?>">
                        Download XLSX
                    </a>
                </p>
            </div>
        <?php endforeach; ?>
    </div>
</section>
