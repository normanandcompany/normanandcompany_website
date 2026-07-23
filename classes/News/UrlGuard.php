<?php
declare(strict_types=1);

final class NewsUrlGuard
{
    public static function assertPublicHttpUrl(string $url): void
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) throw new InvalidArgumentException('Enter a valid feed URL.');
        $parts = parse_url($url);
        if (!in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) throw new InvalidArgumentException('Only HTTP and HTTPS feeds are supported.');
        $host = strtolower($parts['host'] ?? '');
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local')) throw new InvalidArgumentException('Local network feed addresses are not allowed.');
        $addresses = array_values(array_unique(array_merge(gethostbynamel($host) ?: [], self::ipv6Addresses($host))));
        if (!$addresses) throw new InvalidArgumentException('The feed host could not be resolved.');
        foreach ($addresses as $address) {
            if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new InvalidArgumentException('Private or reserved feed addresses are not allowed.');
            }
        }
    }

    private static function ipv6Addresses(string $host): array
    {
        if (!function_exists('dns_get_record')) return [];
        $records = @dns_get_record($host, DNS_AAAA) ?: [];
        return array_values(array_filter(array_column($records, 'ipv6')));
    }
}
