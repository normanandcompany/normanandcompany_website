<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/analytics/helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/services/CloudflareAnalyticsService.php';

requireAdminAnalyticsJson();

try {
    $config = CloudflareAnalyticsConfig::load();
    $range = CloudflareAnalyticsDateRange::fromRequest($_GET, $config->siteTimezone(), $config->maxRangeDays());
    $forceRefresh = isset($_GET['force_refresh']) && $_GET['force_refresh'] === '1';
    $service = new CloudflareAnalyticsService($config);

    sendAnalyticsJson($service->dashboard($range, $forceRefresh));
} catch (InvalidArgumentException $e) {
    sendAnalyticsJson([
        'success' => false,
        'data' => null,
        'meta' => [
            'source' => 'cloudflare'
        ],
        'warnings' => [],
        'error' => $e->getMessage()
    ], 422);
} catch (Throwable $e) {
    error_log('[Cloudflare Analytics] Endpoint failure: ' . substr($e->getMessage(), 0, 300));

    sendAnalyticsJson([
        'success' => false,
        'data' => null,
        'meta' => [
            'source' => 'cloudflare'
        ],
        'warnings' => [],
        'error' => 'Cloudflare analytics could not be loaded right now.'
    ], 500);
}
