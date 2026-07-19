<?php

final class CloudflareAnalyticsDateRange
{
    private string $preset;
    private DateTimeImmutable $startLocal;
    private DateTimeImmutable $endLocal;
    private DateTimeZone $siteTimezone;
    private int $maxDays;

    private function __construct(
        string $preset,
        DateTimeImmutable $startLocal,
        DateTimeImmutable $endLocal,
        DateTimeZone $siteTimezone,
        int $maxDays
    ) {
        $this->preset = $preset;
        $this->startLocal = $startLocal->setTime(0, 0, 0);
        $this->endLocal = $endLocal->setTime(0, 0, 0);
        $this->siteTimezone = $siteTimezone;
        $this->maxDays = $maxDays;
    }

    public static function fromRequest(array $request, string $timezoneName = 'America/Chicago', int $maxDays = 93): self
    {
        $siteTimezone = self::createTimezone($timezoneName);
        $maxDays = max(1, min(366, $maxDays));
        $preset = trim((string)($request['range'] ?? 'yesterday'));
        $today = new DateTimeImmutable('today', $siteTimezone);

        switch ($preset) {
            case 'today':
                $start = $today;
                $end = $today;
                break;

            case 'yesterday':
                $start = $today->modify('-1 day');
                $end = $start;
                break;

            case 'last_30_days':
                $start = $today->modify('-29 days');
                $end = $today;
                break;

            case 'this_month':
                $start = $today->modify('first day of this month');
                $end = $today;
                break;

            case 'last_month':
                $start = $today->modify('first day of previous month');
                $end = $today->modify('last day of previous month');
                break;

            case 'custom':
                $start = self::parseDate((string)($request['start_date'] ?? ''), $siteTimezone, 'Start date');
                $end = self::parseDate((string)($request['end_date'] ?? ''), $siteTimezone, 'End date');
                break;

            case 'last_7_days':
            case '':
                $preset = 'last_7_days';
                $start = $today->modify('-6 days');
                $end = $today;
                break;

            default:
                throw new InvalidArgumentException('Select a valid analytics date range.');
        }

        $range = new self($preset, $start, $end, $siteTimezone, $maxDays);
        $range->validate();

        return $range;
    }

    public function preset(): string
    {
        return $this->preset;
    }

    public function localStartDate(): string
    {
        return $this->startLocal->format('Y-m-d');
    }

    public function localEndDate(): string
    {
        return $this->endLocal->format('Y-m-d');
    }

    public function utcStart(): string
    {
        return $this->startLocal
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s\Z');
    }

    public function utcEndExclusive(): string
    {
        return $this->endLocal
            ->modify('+1 day')
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s\Z');
    }

    public function utcEndInclusive(): string
    {
        return $this->endLocal
            ->modify('+1 day')
            ->modify('-1 second')
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s\Z');
    }

    public function siteTimezoneName(): string
    {
        return $this->siteTimezone->getName();
    }

    public function daysInclusive(): int
    {
        return ((int)$this->startLocal->diff($this->endLocal)->format('%a')) + 1;
    }

    public function groupingInterval(): string
    {
        return $this->daysInclusive() <= 2 ? 'hour' : 'day';
    }

    public function cacheSuffix(): string
    {
        return implode(':', [
            $this->preset,
            $this->localStartDate(),
            $this->localEndDate(),
            $this->groupingInterval(),
            $this->siteTimezoneName(),
            (string)$this->maxDays
        ]);
    }

    public function toMeta(): array
    {
        return [
            'range' => $this->preset,
            'start_date' => $this->localStartDate(),
            'end_date' => $this->localEndDate(),
            'site_timezone' => $this->siteTimezoneName(),
            'cloudflare_timezone' => 'UTC',
            'utc_start' => $this->utcStart(),
            'utc_end_exclusive' => $this->utcEndExclusive(),
            'grouping_interval' => $this->groupingInterval(),
            'max_range_days' => $this->maxDays
        ];
    }

    private static function createTimezone(string $timezoneName): DateTimeZone
    {
        try {
            return new DateTimeZone($timezoneName ?: 'America/Chicago');
        } catch (Throwable $e) {
            return new DateTimeZone('America/Chicago');
        }
    }

    private static function parseDate(string $value, DateTimeZone $timezone, string $fieldName): DateTimeImmutable
    {
        $value = trim($value);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new InvalidArgumentException($fieldName . ' must use YYYY-MM-DD format.');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
        $errors = DateTimeImmutable::getLastErrors();

        if ($date === false || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new InvalidArgumentException($fieldName . ' is not a valid calendar date.');
        }

        return $date->setTime(0, 0, 0);
    }

    private function validate(): void
    {
        if ($this->startLocal > $this->endLocal) {
            throw new InvalidArgumentException('Start date cannot be after end date.');
        }

        if ($this->daysInclusive() > $this->maxDays) {
            throw new InvalidArgumentException('Analytics date range cannot be longer than ' . $this->maxDays . ' days.');
        }
    }
}
