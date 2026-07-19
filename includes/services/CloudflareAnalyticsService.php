<?php

require_once __DIR__ . '/CloudflareAnalyticsConfig.php';
require_once __DIR__ . '/CloudflareAnalyticsDateRange.php';
require_once __DIR__ . '/CloudflareAnalyticsCache.php';

final class CloudflareAnalyticsQueryException extends RuntimeException
{
    private string $category;
    private ?int $httpStatus;

    public function __construct(string $message, string $category, ?int $httpStatus = null)
    {
        parent::__construct($message);
        $this->category = $category;
        $this->httpStatus = $httpStatus;
    }

    public function category(): string
    {
        return $this->category;
    }

    public function httpStatus(): ?int
    {
        return $this->httpStatus;
    }
}

final class CloudflareAnalyticsService
{
    private const GRAPHQL_ENDPOINT = 'https://api.cloudflare.com/client/v4/graphql';
    private const QUERY_VERSION = 'cloudflare-dashboard-v1';
    private const FORCE_REFRESH_MIN_SECONDS = 60;
    private const SECURITY_EVENT_LIMIT = 1000;

    private CloudflareAnalyticsConfig $config;
    private CloudflareAnalyticsCache $cache;

    public function __construct(CloudflareAnalyticsConfig $config)
    {
        $this->config = $config;
        $this->cache = new CloudflareAnalyticsCache($config->cacheDir());
    }

    public function dashboard(CloudflareAnalyticsDateRange $range, bool $forceRefresh = false): array
    {
        $baseMeta = array_merge($range->toMeta(), [
            'source' => 'cloudflare',
            'cached' => false,
            'partial' => false,
            'query_version' => self::QUERY_VERSION
        ]);

        if (!$this->config->isConfigured()) {
            $missing = implode(', ', $this->config->missingKeys());

            return [
                'success' => true,
                'data' => $this->emptyDashboardData('not_configured'),
                'meta' => array_merge($baseMeta, [
                    'configured' => false,
                    'last_refreshed' => null
                ]),
                'warnings' => array_merge(
                    ['Cloudflare analytics is not configured. Add ' . $missing . ' to enable the dashboard.'],
                    $this->config->warnings()
                ),
                'error' => null
            ];
        }

        $cacheKey = $this->cacheKey($range);
        $freshCache = $this->cache->readFresh($cacheKey);

        if ($freshCache !== null) {
            $cacheAge = time() - (int)($freshCache['created_at'] ?? time());

            if (!$forceRefresh || $cacheAge < self::FORCE_REFRESH_MIN_SECONDS) {
                $payload = $freshCache['payload'];
                $payload['meta'] = array_merge($payload['meta'] ?? [], [
                    'cached' => true,
                    'cache_created_at' => gmdate('c', (int)$freshCache['created_at']),
                    'cache_expires_at' => gmdate('c', (int)$freshCache['expires_at'])
                ]);

                if ($forceRefresh && $cacheAge < self::FORCE_REFRESH_MIN_SECONDS) {
                    $payload['warnings'][] = 'Force refresh was throttled because the cached analytics data is less than 60 seconds old.';
                }

                return $payload;
            }
        }

        $warnings = $this->config->warnings();
        $data = $this->emptyDashboardData('available');
        $queryStatus = [];

        $this->loadHttpSummary($range, $data, $queryStatus, $warnings);
        $this->loadCacheBreakdown($range, $data, $queryStatus, $warnings);
        $this->loadHttpTimeseries($range, $data, $queryStatus, $warnings);
        $this->loadCacheTimeseries($range, $data, $queryStatus, $warnings);

        $this->loadBreakdown($range, $data, $queryStatus, $warnings, 'countries', 'clientCountryName', 'Top countries');
        $this->loadBreakdown($range, $data, $queryStatus, $warnings, 'status_codes', 'edgeResponseStatus', 'HTTP status codes');
        $this->loadBreakdown($range, $data, $queryStatus, $warnings, 'hostnames', 'clientRequestHTTPHost', 'Hostnames');
        $this->loadBreakdown($range, $data, $queryStatus, $warnings, 'paths', 'clientRequestPath', 'Top paths');
        $this->loadBreakdown($range, $data, $queryStatus, $warnings, 'browsers', 'userAgentBrowser', 'Browser breakdown');
        $this->loadBreakdown($range, $data, $queryStatus, $warnings, 'devices', 'clientDeviceType', 'Device breakdown');
        $this->loadBreakdown($range, $data, $queryStatus, $warnings, 'operating_systems', 'userAgentOS', 'Operating systems');
        $this->loadSecurityEvents($range, $data, $queryStatus, $warnings);

        $partial = in_array('not_available', $queryStatus, true);
        $hasAvailable = in_array('available', $queryStatus, true);
        $ttl = $this->cacheTtl($range);

        $payload = [
            'success' => true,
            'data' => $data,
            'meta' => array_merge($baseMeta, [
                'configured' => true,
                'cached' => false,
                'partial' => $partial,
                'last_refreshed' => gmdate('c'),
                'cache_ttl_seconds' => $ttl
            ]),
            'warnings' => array_values(array_unique($warnings)),
            'error' => null
        ];

        if ($hasAvailable) {
            $this->cache->write($cacheKey, $payload, $ttl);

            return $payload;
        }

        $staleCache = $this->cache->readStale($cacheKey);

        if ($staleCache !== null) {
            $payload = $staleCache['payload'];
            $payload['meta'] = array_merge($payload['meta'] ?? [], [
                'cached' => true,
                'stale' => true,
                'partial' => true,
                'cache_created_at' => gmdate('c', (int)$staleCache['created_at']),
                'cache_expires_at' => gmdate('c', (int)$staleCache['expires_at'])
            ]);
            $payload['warnings'][] = 'Cloudflare analytics is temporarily unavailable. Showing the last cached dashboard response.';

            return $payload;
        }

        return $payload;
    }

    private function loadHttpSummary(CloudflareAnalyticsDateRange $range, array &$data, array &$queryStatus, array &$warnings): void
    {
        $query = <<<'GRAPHQL'
query CloudflareHttpSummary($zoneTag: string, $filter: filter) {
  viewer {
    zones(filter: { zoneTag: $zoneTag }) {
      summary: httpRequestsAdaptiveGroups(limit: 1, filter: $filter) {
        count
        sum {
          edgeResponseBytes
          visits
        }
      }
    }
  }
}
GRAPHQL;

        $series = $this->safeSeriesQueryChunked('http_summary', 'HTTP summary metrics', $query, $range, 'summary', $queryStatus, $warnings);

        if ($series === null) {
            return;
        }

        $requests = 0;
        $bytes = 0;
        $visits = 0;

        foreach ($series as $row) {
            $requests += $this->intValue($row['count'] ?? 0);
            $bytes += $this->intValue($row['sum']['edgeResponseBytes'] ?? 0);
            $visits += $this->intValue($row['sum']['visits'] ?? 0);
        }

        $data['summary']['requests'] = $this->metric($requests, 'Cloudflare HTTP requests');
        $data['summary']['bandwidth_bytes'] = $this->metric($bytes, 'Bytes transferred');
        $data['summary']['visits'] = $this->metric($visits, 'Cloudflare visits');
    }

    private function loadCacheBreakdown(CloudflareAnalyticsDateRange $range, array &$data, array &$queryStatus, array &$warnings): void
    {
        $query = <<<'GRAPHQL'
query CloudflareCacheBreakdown($zoneTag: string, $filter: filter) {
  viewer {
    zones(filter: { zoneTag: $zoneTag }) {
      cacheStatus: httpRequestsAdaptiveGroups(limit: 40, filter: $filter, orderBy: [count_DESC]) {
        count
        sum {
          edgeResponseBytes
        }
        dimensions {
          cacheStatus
        }
      }
    }
  }
}
GRAPHQL;

        $series = $this->safeSeriesQueryChunked('cache_breakdown', 'Cache status metrics', $query, $range, 'cacheStatus', $queryStatus, $warnings);

        if ($series === null) {
            return;
        }

        $cached = 0;
        $uncached = 0;
        $items = [];

        foreach ($series as $row) {
            $status = trim((string)($row['dimensions']['cacheStatus'] ?? 'Unknown'));
            $count = $this->intValue($row['count'] ?? 0);
            $bytes = $this->intValue($row['sum']['edgeResponseBytes'] ?? 0);
            $isCached = $this->isCachedStatus($status);

            if ($isCached) {
                $cached += $count;
            } else {
                $uncached += $count;
            }

            $items[] = [
                'label' => $status === '' ? 'Unknown' : $status,
                'requests' => $count,
                'bandwidth_bytes' => $bytes,
                'cached' => $isCached
            ];
        }

        $total = $cached + $uncached;
        $hitRate = $total > 0 ? round(($cached / $total) * 100, 1) : 0.0;

        $data['summary']['cached_requests'] = $this->metric($cached, 'Cached requests');
        $data['summary']['uncached_requests'] = $this->metric($uncached, 'Uncached requests');
        $data['summary']['cache_hit_percentage'] = $this->metric($hitRate, 'Cache hit percentage');
        $data['breakdowns']['cache_status'] = $this->dataset($items);
    }

    private function loadHttpTimeseries(CloudflareAnalyticsDateRange $range, array &$data, array &$queryStatus, array &$warnings): void
    {
        $query = <<<'GRAPHQL'
query CloudflareHttpTimeseries($zoneTag: string, $filter: filter) {
  viewer {
    zones(filter: { zoneTag: $zoneTag }) {
      timeseries: httpRequestsAdaptiveGroups(limit: 3000, filter: $filter, orderBy: [datetimeHour_ASC]) {
        count
        sum {
          edgeResponseBytes
          visits
        }
        dimensions {
          datetimeHour
        }
      }
    }
  }
}
GRAPHQL;

        $series = $this->safeSeriesQueryChunked('http_timeseries', 'HTTP time series', $query, $range, 'timeseries', $queryStatus, $warnings);

        if ($series === null) {
            return;
        }

        $points = [];

        foreach ($series as $row) {
            $key = $this->timeBucket((string)($row['dimensions']['datetimeHour'] ?? ''), $range);

            if ($key === '') {
                continue;
            }

            if (!isset($points[$key])) {
                $points[$key] = [
                    'label' => $key,
                    'requests' => 0,
                    'bandwidth_bytes' => 0,
                    'visits' => 0,
                    'cached_requests' => null,
                    'uncached_requests' => null,
                    'security_events' => null
                ];
            }

            $points[$key]['requests'] += $this->intValue($row['count'] ?? 0);
            $points[$key]['bandwidth_bytes'] += $this->intValue($row['sum']['edgeResponseBytes'] ?? 0);
            $points[$key]['visits'] += $this->intValue($row['sum']['visits'] ?? 0);
        }

        ksort($points);

        $data['timeseries'] = [
            'status' => 'available',
            'interval' => $range->groupingInterval(),
            'points' => array_values($points),
            'reason' => null
        ];
    }

    private function loadCacheTimeseries(CloudflareAnalyticsDateRange $range, array &$data, array &$queryStatus, array &$warnings): void
    {
        $query = <<<'GRAPHQL'
query CloudflareCacheTimeseries($zoneTag: string, $filter: filter) {
  viewer {
    zones(filter: { zoneTag: $zoneTag }) {
      cacheTimeseries: httpRequestsAdaptiveGroups(limit: 6000, filter: $filter, orderBy: [datetimeHour_ASC]) {
        count
        dimensions {
          datetimeHour
          cacheStatus
        }
      }
    }
  }
}
GRAPHQL;

        $series = $this->safeSeriesQueryChunked('cache_timeseries', 'Cache time series', $query, $range, 'cacheTimeseries', $queryStatus, $warnings);

        if ($series === null || ($data['timeseries']['status'] ?? '') !== 'available') {
            return;
        }

        $byBucket = [];

        foreach ($series as $row) {
            $key = $this->timeBucket((string)($row['dimensions']['datetimeHour'] ?? ''), $range);

            if ($key === '') {
                continue;
            }

            if (!isset($byBucket[$key])) {
                $byBucket[$key] = ['cached' => 0, 'uncached' => 0];
            }

            if ($this->isCachedStatus((string)($row['dimensions']['cacheStatus'] ?? ''))) {
                $byBucket[$key]['cached'] += $this->intValue($row['count'] ?? 0);
            } else {
                $byBucket[$key]['uncached'] += $this->intValue($row['count'] ?? 0);
            }
        }

        foreach ($data['timeseries']['points'] as &$point) {
            $bucket = $byBucket[$point['label']] ?? null;

            if ($bucket !== null) {
                $point['cached_requests'] = $bucket['cached'];
                $point['uncached_requests'] = $bucket['uncached'];
            }
        }
        unset($point);
    }

    private function loadBreakdown(
        CloudflareAnalyticsDateRange $range,
        array &$data,
        array &$queryStatus,
        array &$warnings,
        string $key,
        string $dimension,
        string $label
    ): void {
        $allowedDimensions = [
            'clientCountryName',
            'edgeResponseStatus',
            'clientRequestHTTPHost',
            'clientRequestPath',
            'userAgentBrowser',
            'clientDeviceType',
            'userAgentOS'
        ];

        if (!in_array($dimension, $allowedDimensions, true)) {
            return;
        }

        $query = sprintf(<<<'GRAPHQL'
query CloudflareBreakdown($zoneTag: string, $filter: filter) {
  viewer {
    zones(filter: { zoneTag: $zoneTag }) {
      breakdown: httpRequestsAdaptiveGroups(limit: 12, filter: $filter, orderBy: [count_DESC]) {
        count
        sum {
          edgeResponseBytes
          visits
        }
        dimensions {
          metric: %s
        }
      }
    }
  }
}
GRAPHQL, $dimension);

        $series = $this->safeSeriesQueryChunked('breakdown_' . $key, $label, $query, $range, 'breakdown', $queryStatus, $warnings);

        if ($series === null) {
            return;
        }

        $totals = [];

        foreach ($series as $row) {
            $metric = $row['dimensions']['metric'] ?? 'Unknown';
            $metricLabel = trim((string)$metric) === '' ? 'Unknown' : (string)$metric;

            if (!isset($totals[$metricLabel])) {
                $totals[$metricLabel] = [
                    'label' => $metricLabel,
                    'requests' => 0,
                    'bandwidth_bytes' => 0,
                    'visits' => 0
                ];
            }

            $totals[$metricLabel]['requests'] += $this->intValue($row['count'] ?? 0);
            $totals[$metricLabel]['bandwidth_bytes'] += $this->intValue($row['sum']['edgeResponseBytes'] ?? 0);
            $totals[$metricLabel]['visits'] += $this->intValue($row['sum']['visits'] ?? 0);
        }

        $items = array_values($totals);
        usort($items, fn(array $a, array $b): int => $b['requests'] <=> $a['requests']);

        $data['breakdowns'][$key] = $this->dataset(array_slice($items, 0, 12));
    }

    private function loadSecurityEvents(CloudflareAnalyticsDateRange $range, array &$data, array &$queryStatus, array &$warnings): void
    {
        $query = <<<'GRAPHQL'
query CloudflareSecurityEvents($zoneTag: string, $filter: FirewallEventsAdaptiveFilter_InputObject) {
  viewer {
    zones(filter: { zoneTag: $zoneTag }) {
      securityEvents: firewallEventsAdaptive(filter: $filter, limit: 1000, orderBy: [datetime_DESC]) {
        action
        datetime
        source
        clientCountryName
        clientRequestPath
      }
    }
  }
}
GRAPHQL;

        $series = $this->safeSeriesQueryChunked('security_events', 'Security events', $query, $range, 'securityEvents', $queryStatus, $warnings, true);

        if ($series === null) {
            return;
        }

        $actions = [];
        $byTime = [];
        $blocked = 0;
        $challenged = 0;

        foreach ($series as $event) {
            $action = trim((string)($event['action'] ?? 'Unknown'));
            $actionKey = $action === '' ? 'Unknown' : $action;
            $actions[$actionKey] = ($actions[$actionKey] ?? 0) + 1;

            if ($this->isBlockedAction($action)) {
                $blocked++;
            }

            if ($this->isChallengedAction($action)) {
                $challenged++;
            }

            $bucket = $this->timeBucket((string)($event['datetime'] ?? ''), $range);

            if ($bucket !== '') {
                $byTime[$bucket] = ($byTime[$bucket] ?? 0) + 1;
            }
        }

        $items = [];

        foreach ($actions as $action => $count) {
            $items[] = [
                'label' => $action,
                'events' => $count
            ];
        }

        usort($items, fn(array $a, array $b): int => $b['events'] <=> $a['events']);

        $data['summary']['security_events'] = $this->metric(count($series), 'Security events returned by Cloudflare');
        $data['summary']['blocked_requests'] = $this->metric($blocked, 'Blocked security actions');
        $data['summary']['challenged_requests'] = $this->metric($challenged, 'Challenged security actions');
        $data['breakdowns']['security_actions'] = $this->dataset($items);

        if (($data['timeseries']['status'] ?? '') === 'available') {
            foreach ($data['timeseries']['points'] as &$point) {
                $point['security_events'] = $byTime[$point['label']] ?? 0;
            }
            unset($point);
        }

        if (count($series) >= self::SECURITY_EVENT_LIMIT) {
            $warnings[] = 'Security events reached the response limit, so security counts may be partial for this range.';
        }
    }

    private function safeSeriesQuery(
        string $queryKey,
        string $label,
        string $query,
        array $variables,
        string $seriesKey,
        array &$queryStatus,
        array &$warnings
    ): ?array {
        try {
            $response = $this->requestGraphql($queryKey, $query, $variables);
            $series = $this->extractSeries($response, $seriesKey);
            $queryStatus[] = 'available';

            return $series;
        } catch (CloudflareAnalyticsQueryException $e) {
            $queryStatus[] = 'not_available';
            $warnings[] = $this->safeWarning($label, $e);
            $this->logQueryIssue($queryKey, $e, $variables);

            return null;
        }
    }

    private function safeSeriesQueryChunked(
        string $queryKey,
        string $label,
        string $query,
        CloudflareAnalyticsDateRange $range,
        string $seriesKey,
        array &$queryStatus,
        array &$warnings,
        bool $securityQuery = false
    ): ?array {
        $series = [];
        $errors = [];
        $chunks = $this->rangeChunks($range);

        foreach ($chunks as $chunkIndex => $chunk) {
            try {
                $response = $this->requestGraphql(
                    $queryKey . '_chunk_' . ($chunkIndex + 1),
                    $query,
                    $securityQuery
                        ? $this->securityVariables($range, $chunk)
                        : $this->httpVariables($range, $chunk)
                );
                $series = array_merge($series, $this->extractSeries($response, $seriesKey));
            } catch (CloudflareAnalyticsQueryException $e) {
                $errors[] = $e;
                $this->logQueryIssue(
                    $queryKey . '_chunk_' . ($chunkIndex + 1),
                    $e,
                    $securityQuery
                        ? $this->securityVariables($range, $chunk)
                        : $this->httpVariables($range, $chunk)
                );
            }
        }

        if ($series !== []) {
            $queryStatus[] = 'available';

            if ($errors !== []) {
                $queryStatus[] = 'not_available';
                $warnings[] = $label . ' loaded partially because one or more Cloudflare date chunks were unavailable.';
            }

            return $series;
        }

        $queryStatus[] = 'not_available';
        $firstError = $errors[0] ?? new CloudflareAnalyticsQueryException('Cloudflare did not return data.', 'missing_dataset');
        $warnings[] = $this->safeWarning($label, $firstError);

        return null;
    }

    private function requestGraphql(string $queryKey, string $query, array $variables): array
    {
        if (!function_exists('curl_init')) {
            throw new CloudflareAnalyticsQueryException('cURL is not available on this PHP installation.', 'curl_missing');
        }

        $json = json_encode([
            'query' => $query,
            'variables' => $variables
        ], JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new CloudflareAnalyticsQueryException('Unable to encode GraphQL request.', 'request_encoding');
        }

        $curl = curl_init(self::GRAPHQL_ENDPOINT);

        if ($curl === false) {
            throw new CloudflareAnalyticsQueryException('Unable to initialize Cloudflare request.', 'curl_init');
        }

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->config->connectTimeoutSeconds(),
            CURLOPT_TIMEOUT => $this->config->requestTimeoutSeconds(),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->config->apiToken(),
                'Accept: application/json',
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => $json
        ]);

        $body = curl_exec($curl);
        $curlError = curl_error($curl);
        $httpStatus = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($body === false) {
            throw new CloudflareAnalyticsQueryException($curlError ?: 'Cloudflare request failed.', 'curl', null);
        }

        if ($httpStatus < 200 || $httpStatus >= 300) {
            throw new CloudflareAnalyticsQueryException('Cloudflare returned HTTP ' . $httpStatus . '.', 'http', $httpStatus);
        }

        $decoded = json_decode((string)$body, true);

        if (!is_array($decoded)) {
            throw new CloudflareAnalyticsQueryException('Cloudflare returned malformed JSON.', 'malformed_json', $httpStatus);
        }

        if (isset($decoded['errors']) && is_array($decoded['errors']) && count($decoded['errors']) > 0) {
            $message = (string)($decoded['errors'][0]['message'] ?? 'Cloudflare GraphQL error.');
            throw new CloudflareAnalyticsQueryException($message, 'graphql', $httpStatus);
        }

        if (isset($decoded['success']) && $decoded['success'] === false) {
            throw new CloudflareAnalyticsQueryException('Cloudflare API request was not successful.', 'api', $httpStatus);
        }

        return $decoded;
    }

    private function extractSeries(array $response, string $seriesKey): array
    {
        $zones = $response['data']['viewer']['zones'] ?? null;

        if (!is_array($zones) || count($zones) === 0 || !is_array($zones[0] ?? null)) {
            throw new CloudflareAnalyticsQueryException('Cloudflare did not return a matching zone.', 'zone');
        }

        $series = $zones[0][$seriesKey] ?? null;

        if (!is_array($series)) {
            throw new CloudflareAnalyticsQueryException('Cloudflare did not return the requested analytics dataset.', 'missing_dataset');
        }

        return $series;
    }

    private function httpVariables(CloudflareAnalyticsDateRange $range, ?array $chunk = null): array
    {
        $start = $chunk['utc_start'] ?? $range->utcStart();
        $end = $chunk['utc_end_exclusive'] ?? $range->utcEndExclusive();

        return [
            'zoneTag' => $this->config->zoneId(),
            'filter' => [
                'datetime_geq' => $start,
                'datetime_lt' => $end,
                'requestSource' => 'eyeball'
            ]
        ];
    }

    private function securityVariables(CloudflareAnalyticsDateRange $range, ?array $chunk = null): array
    {
        $start = $chunk['utc_start'] ?? $range->utcStart();
        $end = $chunk['utc_end_inclusive'] ?? $range->utcEndInclusive();

        return [
            'zoneTag' => $this->config->zoneId(),
            'filter' => [
                'datetime_geq' => $start,
                'datetime_leq' => $end
            ]
        ];
    }

    private function rangeChunks(CloudflareAnalyticsDateRange $range): array
    {
        $siteTimezone = new DateTimeZone($range->siteTimezoneName());
        $utcTimezone = new DateTimeZone('UTC');
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $range->localStartDate(), $siteTimezone);
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $range->localEndDate(), $siteTimezone);

        if ($start === false || $end === false) {
            return [[
                'utc_start' => $range->utcStart(),
                'utc_end_exclusive' => $range->utcEndExclusive(),
                'utc_end_inclusive' => $range->utcEndInclusive()
            ]];
        }

        $chunks = [];

        for ($day = $start; $day <= $end; $day = $day->modify('+1 day')) {
            $nextDay = $day->modify('+1 day');
            $chunks[] = [
                'utc_start' => $day->setTimezone($utcTimezone)->format('Y-m-d\TH:i:s\Z'),
                'utc_end_exclusive' => $nextDay->setTimezone($utcTimezone)->format('Y-m-d\TH:i:s\Z'),
                'utc_end_inclusive' => $nextDay->modify('-1 second')->setTimezone($utcTimezone)->format('Y-m-d\TH:i:s\Z')
            ];
        }

        return $chunks;
    }

    private function emptyDashboardData(string $status): array
    {
        $notAvailable = 'Metric is not available for this Cloudflare plan, dataset, or configuration.';

        return [
            'status' => $status,
            'summary' => [
                'requests' => $this->notAvailableMetric($notAvailable),
                'bandwidth_bytes' => $this->notAvailableMetric($notAvailable),
                'visits' => $this->notAvailableMetric($notAvailable),
                'unique_ips' => $this->notAvailableMetric('Unique IP analytics are not requested from the current Cloudflare GraphQL dataset.'),
                'cached_requests' => $this->notAvailableMetric($notAvailable),
                'uncached_requests' => $this->notAvailableMetric($notAvailable),
                'cache_hit_percentage' => $this->notAvailableMetric($notAvailable),
                'origin_requests' => $this->notAvailableMetric('Origin request counts are not exposed by the current dashboard query.'),
                'security_events' => $this->notAvailableMetric($notAvailable),
                'blocked_requests' => $this->notAvailableMetric($notAvailable),
                'challenged_requests' => $this->notAvailableMetric($notAvailable)
            ],
            'timeseries' => $this->notAvailableDataset($notAvailable),
            'breakdowns' => [
                'countries' => $this->notAvailableDataset($notAvailable),
                'status_codes' => $this->notAvailableDataset($notAvailable),
                'hostnames' => $this->notAvailableDataset($notAvailable),
                'paths' => $this->notAvailableDataset($notAvailable),
                'browsers' => $this->notAvailableDataset($notAvailable),
                'devices' => $this->notAvailableDataset($notAvailable),
                'operating_systems' => $this->notAvailableDataset($notAvailable),
                'cache_status' => $this->notAvailableDataset($notAvailable),
                'security_actions' => $this->notAvailableDataset($notAvailable)
            ],
            'notes' => [
                'HTTP requests are edge requests reported by Cloudflare, not people or page views.',
                'Cloudflare visits are a Cloudflare HTTP analytics metric and are not exact unique visitors.',
                'One person can use multiple IP addresses, several people can share one IP address, and automated traffic may be included.'
            ]
        ];
    }

    private function metric(int|float $value, string $label): array
    {
        return [
            'status' => 'available',
            'value' => $value,
            'label' => $label,
            'reason' => null
        ];
    }

    private function notAvailableMetric(string $reason): array
    {
        return [
            'status' => 'not_available',
            'value' => null,
            'reason' => $reason
        ];
    }

    private function dataset(array $items): array
    {
        return [
            'status' => 'available',
            'items' => $items,
            'reason' => null
        ];
    }

    private function notAvailableDataset(string $reason): array
    {
        return [
            'status' => 'not_available',
            'items' => [],
            'reason' => $reason
        ];
    }

    private function intValue(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int)round($value);
        }

        if (is_string($value) && is_numeric($value)) {
            return (int)round((float)$value);
        }

        return 0;
    }

    private function timeBucket(string $datetime, CloudflareAnalyticsDateRange $range): string
    {
        if ($datetime === '') {
            return '';
        }

        try {
            $date = new DateTimeImmutable($datetime, new DateTimeZone('UTC'));
            $local = $date->setTimezone(new DateTimeZone($range->siteTimezoneName()));
        } catch (Throwable $e) {
            return '';
        }

        return $range->groupingInterval() === 'hour'
            ? $local->format('Y-m-d H:00')
            : $local->format('Y-m-d');
    }

    private function isCachedStatus(string $status): bool
    {
        return in_array(strtolower($status), ['hit', 'stale', 'updating', 'revalidated'], true);
    }

    private function isBlockedAction(string $action): bool
    {
        return in_array(strtolower($action), ['block', 'blocked'], true);
    }

    private function isChallengedAction(string $action): bool
    {
        return in_array(strtolower($action), ['challenge', 'jschallenge', 'managed_challenge', 'interactivechallenge'], true);
    }

    private function cacheKey(CloudflareAnalyticsDateRange $range): string
    {
        return implode(':', [
            self::QUERY_VERSION,
            $this->config->zoneHash(),
            'dashboard',
            $range->cacheSuffix()
        ]);
    }

    private function cacheTtl(CloudflareAnalyticsDateRange $range): int
    {
        $timezone = new DateTimeZone($range->siteTimezoneName());
        $today = new DateTimeImmutable('today', $timezone);
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $range->localEndDate(), $timezone) ?: $today;

        if ($end >= $today) {
            return 10 * 60;
        }

        if ($end >= $today->modify('-7 days')) {
            return 2 * 60 * 60;
        }

        return 24 * 60 * 60;
    }

    private function safeWarning(string $label, CloudflareAnalyticsQueryException $e): string
    {
        if ($e->httpStatus() === 401 || $e->httpStatus() === 403 || $e->category() === 'zone') {
            return 'Cloudflare rejected the analytics request. Check the API token permissions and Zone ID.';
        }

        if ($e->httpStatus() === 429) {
            return 'Cloudflare rate-limited the analytics request. Cached data will be used when available.';
        }

        if ($e->category() === 'graphql' || $e->category() === 'missing_dataset') {
            return $label . ' are not available for this Cloudflare plan or GraphQL dataset.';
        }

        return $label . ' could not be loaded from Cloudflare right now.';
    }

    private function logQueryIssue(string $queryKey, CloudflareAnalyticsQueryException $e, array $variables): void
    {
        $filter = $variables['filter'] ?? [];
        $context = [
            'category' => $e->category(),
            'http_status' => $e->httpStatus(),
            'query_type' => $queryKey,
            'zone_hash' => substr($this->config->zoneHash(), 0, 16),
            'start' => is_array($filter) ? ($filter['datetime_geq'] ?? null) : null,
            'end' => is_array($filter) ? ($filter['datetime_lt'] ?? $filter['datetime_leq'] ?? null) : null,
            'message' => $this->sanitizeLogMessage($e->getMessage())
        ];

        error_log('[Cloudflare Analytics] ' . json_encode($context, JSON_UNESCAPED_SLASHES));
    }

    private function sanitizeLogMessage(string $message): string
    {
        $message = preg_replace('/Bearer\s+[A-Za-z0-9._~+\/=-]+/i', 'Bearer [redacted]', $message);
        $message = preg_replace('/[A-Fa-f0-9]{32,}/', '[redacted-id]', (string)$message);

        return substr((string)$message, 0, 300);
    }
}
