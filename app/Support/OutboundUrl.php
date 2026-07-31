<?php

namespace App\Support;

/**
 * Guard for URLs the server itself fetches on a caller's behalf.
 *
 * Try tokens are handed out from the homepage with no account, so any URL an
 * agent passes to create_review / add_screenshot / resolve_marks would
 * otherwise be fetched from inside the app container — reaching loopback,
 * the private network, and cloud metadata endpoints. Everything here is a
 * reachability check; it says nothing about whether the bytes are an image
 * (ScreenshotStorage still decides that).
 *
 * Not covered on purpose: page_url capture (rendered by the external
 * Browserless host, not this container) and webhook_url (a blind push of data
 * the token holder already has). If that stance changes, route both through
 * assertFetchable() rather than adding a second set of rules.
 */
class OutboundUrl
{
    /** Redirect hops we will follow, each re-checked before the next request. */
    public const MAX_REDIRECTS = 3;

    /**
     * Why a URL must not be fetched, or null when it is safe.
     */
    public static function reasonToReject(string $url): ?string
    {
        $url = trim($url);

        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return 'that does not look like a valid URL';
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'])) {
            return 'that does not look like a valid URL';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if (! in_array($scheme, self::allowedSchemes(), true)) {
            return 'only '.implode('/', self::allowedSchemes()).' URLs can be fetched';
        }

        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        if (! in_array($port, [80, 443], true)) {
            return 'only ports 80 and 443 can be fetched';
        }

        $host = trim($parts['host'], '[]');

        foreach (self::resolve($host) as $ip) {
            if (self::isBlockedIp($ip)) {
                return 'that host resolves to a private or loopback address';
            }
        }

        return null;
    }

    public static function isAllowed(string $url): bool
    {
        return self::reasonToReject($url) === null;
    }

    /**
     * Every IP a host resolves to. An unresolvable host yields no addresses,
     * which we treat as allowed — the request will simply fail on its own.
     *
     * @return list<string>
     */
    protected static function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = gethostbynamel($host) ?: [];

        // AAAA too — an A record pointing somewhere public says nothing about
        // where the host's IPv6 address goes, and curl may prefer v6.
        foreach ((array) @dns_get_record($host, DNS_AAAA) as $record) {
            if (isset($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }

    /**
     * Loopback, private, reserved, and link-local addresses. Link-local is
     * called out separately because 169.254.169.254 is the cloud metadata
     * endpoint and FILTER_FLAG_NO_RES_RANGE does not cover it everywhere.
     */
    protected static function isBlockedIp(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return true;
        }

        // 169.254.0.0/16 (IPv4 link-local incl. cloud metadata) and fe80::/10.
        if (str_starts_with($ip, '169.254.')) {
            return true;
        }

        $lower = strtolower($ip);

        if (str_starts_with($lower, 'fe8') || str_starts_with($lower, 'fe9')
            || str_starts_with($lower, 'fea') || str_starts_with($lower, 'feb')) {
            return true;
        }

        // 100.64.0.0/10 (CGNAT) — reachable inside some hosting networks.
        if (preg_match('/^100\.(6[4-9]|[7-9]\d|1[01]\d|12[0-7])\./', $ip)) {
            return true;
        }

        return false;
    }

    /**
     * https everywhere; http only where local fixtures and tests need it.
     * Mirrors ReviewService::assertValidWebhookUrl().
     *
     * @return list<string>
     */
    protected static function allowedSchemes(): array
    {
        return app()->environment(['local', 'testing']) ? ['https', 'http'] : ['https'];
    }
}
