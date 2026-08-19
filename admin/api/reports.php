<?php

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';
require_once __DIR__ . '/report_catalog.php';
require_once __DIR__ . '/xlsx_writer.php';

if (!isLoggedIn()) {
    http_response_code(401);
    exit('Administrator login required.');
}

if (getUserRole() !== 'admin') {
    http_response_code(403);
    exit('Administrator access required.');
}

function refreshCloudflareReportSnapshot(PDO $pdo): void
{
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/services/CloudflareAnalyticsService.php';

    $config = CloudflareAnalyticsConfig::load();
    $range = CloudflareAnalyticsDateRange::fromRequest(
        ['range' => 'last_30_days'],
        $config->siteTimezone(),
        $config->maxRangeDays()
    );
    $payload = (new CloudflareAnalyticsService($config))->dashboard($range);
    $points = $payload['data']['timeseries']['points'] ?? [];

    if (!is_array($points) || $points === []) {
        return;
    }

    $statement = $pdo->prepare("INSERT INTO cloudflare_analytics_daily (
        metric_date, requests, visits, bandwidth_bytes, cached_requests,
        uncached_requests, security_events, captured_at
    ) VALUES (
        :metric_date, :requests, :visits, :bandwidth_bytes, :cached_requests,
        :uncached_requests, :security_events, NOW()
    ) ON DUPLICATE KEY UPDATE
        requests = VALUES(requests),
        visits = VALUES(visits),
        bandwidth_bytes = VALUES(bandwidth_bytes),
        cached_requests = VALUES(cached_requests),
        uncached_requests = VALUES(uncached_requests),
        security_events = VALUES(security_events),
        captured_at = NOW()");

    foreach ($points as $point) {
        $label = substr((string) ($point['label'] ?? ''), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $label)) {
            continue;
        }

        $statement->execute([
            'metric_date' => $label,
            'requests' => (int) ($point['requests'] ?? 0),
            'visits' => (int) ($point['visits'] ?? 0),
            'bandwidth_bytes' => (int) ($point['bandwidth_bytes'] ?? 0),
            'cached_requests' => isset($point['cached_requests']) ? (int) $point['cached_requests'] : null,
            'uncached_requests' => isset($point['uncached_requests']) ? (int) $point['uncached_requests'] : null,
            'security_events' => isset($point['security_events']) ? (int) $point['security_events'] : null
        ]);
    }
}

function fetchReportData(PDO $pdo, string $procedure): array
{
    if (!preg_match('/^sp_report_[a-z_]+$/', $procedure)) {
        throw new InvalidArgumentException('Invalid report procedure.');
    }

    $statement = $pdo->query('CALL ' . $procedure . '()');
    $columns = [];
    for ($index = 0; $index < $statement->columnCount(); $index++) {
        $metadata = $statement->getColumnMeta($index);
        $columns[] = (string) ($metadata['name'] ?? ('column_' . ($index + 1)));
    }

    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    $statement->closeCursor();

    return ['columns' => $columns, 'rows' => $rows];
}

try {
    $catalog = adminReportCatalog();
    $reportKey = trim((string) ($_GET['report'] ?? ''));

    if (!isset($catalog[$reportKey])) {
        throw new InvalidArgumentException('Select a valid report.');
    }

    require_once __DIR__ . '/db.php';
    $report = $catalog[$reportKey];

    if ($reportKey === 'cloudflare') {
        refreshCloudflareReportSnapshot($pdo);
    }

    $data = fetchReportData($pdo, $report['procedure']);
    $workbook = buildReportWorkbook($report['title'], $report['description'], $data['rows'], $data['columns']);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $report['filename'] . '"');
    header('Content-Length: ' . strlen($workbook));
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    echo $workbook;
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $e->getMessage();
} catch (Throwable $e) {
    error_log('[Admin Reports] ' . substr($e->getMessage(), 0, 500));
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'The report could not be generated right now.';
}
