<?php

namespace App\Domain\Web\Safety;

use App\Domain\Web\Exceptions\WebProviderException;
use App\Models\AppSetting;

/**
 * SSRF guard for direct and provider-backed fetches.
 *
 * Every URL sent to a direct HTTP request or external reader provider must pass
 * this guard before network I/O. Redirect targets must be validated too because
 * an initially public URL can otherwise bounce to internal infrastructure.
 */
class WebRequestGuard
{
    /**
     * @param  list<string>|null  $allowedPrivateHosts
     */
    public function __construct(private ?array $allowedPrivateHosts = null) {}

    /** @var list<string> */
    private const BLOCKED_HOSTS = [
        'localhost',
        'metadata.google.internal',
        'metadata.goog',
    ];

    /** @var list<string> */
    private const BLOCKED_IPS = [
        '169.254.169.254',
        '169.254.170.2',
        '169.254.169.253',
        '100.100.100.200',
    ];

    /**
     * Validate a URL before any web provider touches it.
     *
     * Direct fetch can opt into a narrow workspace-configured private-host
     * allowance for local development targets such as opencompany.test.
     * Provider-backed readers/search extractors should keep the default public
     * guard so private URLs are never sent to third-party services.
     */
    public function assertSafePublicUrl(string $url, bool $allowConfiguredPrivateHosts = false): void
    {
        if (mb_strlen($url) > 2048) {
            throw new WebProviderException('URL is too long.');
        }

        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw new WebProviderException('URL is invalid.');
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new WebProviderException('Only http and https URLs are allowed.');
        }

        if (($parts['user'] ?? '') !== '' || ($parts['pass'] ?? '') !== '') {
            throw new WebProviderException('URLs with embedded credentials are not allowed.');
        }

        $host = strtolower(rtrim((string) $parts['host'], '.'));
        if ($host === '' || in_array($host, self::BLOCKED_HOSTS, true) || str_ends_with($host, '.localhost')) {
            throw new WebProviderException("Blocked internal hostname: {$host}");
        }

        $allowPrivateHost = $allowConfiguredPrivateHosts && $this->allowsConfiguredPrivateHost($host);
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolveIpAddresses($host);
        if ($ips === []) {
            throw new WebProviderException("Could not resolve host '{$host}'.");
        }

        foreach ($ips as $ip) {
            if ($this->isPrivateOrReservedIp($ip)) {
                if ($allowPrivateHost && ! $this->isAlwaysBlockedIp($ip)) {
                    continue;
                }

                throw new WebProviderException("Blocked private or reserved address for host '{$host}'.");
            }
        }
    }

    /**
     * Validate a URL against an explicit private-target allowlist and return
     * the vetted connection target.
     *
     * Used by callers that are not web tools (MCP servers) and therefore must
     * not inherit the workspace `web_fetch_allowed_private_hosts` setting. The
     * allowlist is operator config only (never agent- or workspace-editable).
     *
     * Entries may be:
     * - an exact host name (`mcp.internal`, `localhost`; scheme/path/port are
     *   ignored) — permits that host to resolve to private addresses, and
     *   permits otherwise-blocked local hostnames such as `localhost`;
     * - an IP address or CIDR (`10.0.0.0/8`, `fd00::/8`, `127.0.0.1`) —
     *   permits private addresses inside that range for any host name.
     *
     * Invariants (not overridable by any allowlist entry): only http/https,
     * no embedded credentials, cloud metadata hostnames and link-local /
     * metadata IPs (169.254.0.0/16, fe80::/10, provider metadata IPs) stay
     * blocked, and every resolved address must pass, so a host with one
     * public and one disallowed private record is rejected.
     *
     * @param  list<string>  $allowedPrivateTargets
     * @return array{host: string, port: int, ips: list<string>}
     *
     * @throws WebProviderException when the URL or any resolved address is not allowed
     */
    public function assertSafeUrlWithAllowlist(string $url, array $allowedPrivateTargets = []): array
    {
        if (mb_strlen($url) > 2048) {
            throw new WebProviderException('URL is too long.');
        }

        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw new WebProviderException('URL is invalid.');
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new WebProviderException('Only http and https URLs are allowed.');
        }

        if (($parts['user'] ?? '') !== '' || ($parts['pass'] ?? '') !== '') {
            throw new WebProviderException('URLs with embedded credentials are not allowed.');
        }

        $host = strtolower(rtrim(trim((string) $parts['host'], '[]'), '.'));
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        [$allowedHosts, $allowedRanges] = $this->splitAllowlist($allowedPrivateTargets);
        $hostAllowlisted = in_array($host, $allowedHosts, true);

        // Metadata hostnames are never allowlistable; other local names
        // (localhost, *.localhost) only pass when explicitly listed.
        if ($host === '' || in_array($host, ['metadata.google.internal', 'metadata.goog'], true)) {
            throw new WebProviderException("Blocked internal hostname: {$host}");
        }
        if (! $hostAllowlisted && (in_array($host, self::BLOCKED_HOSTS, true) || str_ends_with($host, '.localhost'))) {
            throw new WebProviderException("Blocked internal hostname: {$host}");
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolveIpAddresses($host);
        if ($ips === []) {
            throw new WebProviderException("Could not resolve host '{$host}'.");
        }

        foreach ($ips as $ip) {
            if ($this->isAlwaysBlockedIp($ip)) {
                throw new WebProviderException("Blocked private or reserved address for host '{$host}'.");
            }

            if (! $this->isPrivateOrReservedIp($ip)) {
                continue;
            }

            if ($hostAllowlisted || $this->ipInAnyRange($ip, $allowedRanges)) {
                continue;
            }

            throw new WebProviderException("Blocked private or reserved address for host '{$host}'.");
        }

        return ['host' => $host, 'port' => $port, 'ips' => array_values($ips)];
    }

    /**
     * @param  list<mixed>  $entries
     * @return array{0: list<string>, 1: list<string>}
     */
    private function splitAllowlist(array $entries): array
    {
        $hosts = [];
        $ranges = [];

        foreach ($entries as $entry) {
            if (! is_string($entry) || trim($entry) === '') {
                continue;
            }

            $entry = strtolower(trim($entry));

            if (str_contains($entry, '/') && ! str_contains($entry, '://')
                && filter_var(explode('/', $entry, 2)[0], FILTER_VALIDATE_IP)) {
                $ranges[] = $entry;

                continue;
            }

            if (filter_var(trim($entry, '[]'), FILTER_VALIDATE_IP)) {
                $ranges[] = trim($entry, '[]');

                continue;
            }

            $entry = preg_replace('#^[a-z][a-z0-9+.-]*://#', '', $entry) ?? $entry;
            $entry = explode('/', $entry, 2)[0];
            $entry = preg_replace('#:\d+$#', '', $entry) ?? $entry;
            $entry = rtrim($entry, '.');

            if ($entry !== '') {
                $hosts[] = $entry;
            }
        }

        return [$hosts, $ranges];
    }

    /**
     * @param  list<string>  $ranges  IPs or CIDRs (IPv4 or IPv6)
     */
    private function ipInAnyRange(string $ip, array $ranges): bool
    {
        $packedIp = @inet_pton($ip);
        if ($packedIp === false) {
            return false;
        }

        foreach ($ranges as $range) {
            [$network, $bits] = str_contains($range, '/') ? explode('/', $range, 2) : [$range, null];
            $packedNet = @inet_pton($network);
            if ($packedNet === false || strlen($packedNet) !== strlen($packedIp)) {
                continue;
            }

            $maxBits = strlen($packedIp) * 8;
            $bits = $bits === null ? $maxBits : (int) $bits;
            if ($bits < 0 || $bits > $maxBits) {
                continue;
            }

            $fullBytes = intdiv($bits, 8);
            if (substr($packedIp, 0, $fullBytes) !== substr($packedNet, 0, $fullBytes)) {
                continue;
            }

            $remainder = $bits % 8;
            if ($remainder === 0) {
                return true;
            }

            $mask = (0xFF << (8 - $remainder)) & 0xFF;
            if ((ord($packedIp[$fullBytes]) & $mask) === (ord($packedNet[$fullBytes]) & $mask)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    protected function resolveIpAddresses(string $host): array
    {
        $ips = [];

        $v4 = @gethostbynamel($host);
        if (is_array($v4)) {
            foreach ($v4 as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    $ips[] = $ip;
                }
            }
        }

        $records = @dns_get_record($host, DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $record) {
                $ipv6 = $record['ipv6'] ?? null;
                if (is_string($ipv6) && filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    $ips[] = $ipv6;
                }
            }
        }

        return array_values(array_unique($ips));
    }

    private function isPrivateOrReservedIp(string $ip): bool
    {
        if ($this->isAlwaysBlockedIp($ip)) {
            return true;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $long = ip2long($ip);
            if ($long === false) {
                return true;
            }

            return $this->ipv4InRange($long, '100.64.0.0', 10)
                || $this->ipv4InRange($long, '169.254.0.0', 16);
        }

        $lower = strtolower($ip);

        return str_starts_with($lower, 'fe80:')
            || str_starts_with($lower, 'fd00:')
            || str_starts_with($lower, 'fc00:');
    }

    private function isAlwaysBlockedIp(string $ip): bool
    {
        if (in_array($ip, self::BLOCKED_IPS, true)) {
            return true;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $long = ip2long($ip);

            return $long === false || $this->ipv4InRange($long, '169.254.0.0', 16);
        }

        return str_starts_with(strtolower($ip), 'fe80:');
    }

    private function allowsConfiguredPrivateHost(string $host): bool
    {
        $host = strtolower(trim($host));
        $configured = $this->allowedPrivateHosts;

        if ($configured === null) {
            $configured = function_exists('config') ? config('web.fetch.allowed_private_hosts', []) : [];
        }

        if (function_exists('app') && app()->bound('currentWorkspace')) {
            $configured = AppSetting::getValue('web_fetch_allowed_private_hosts', $configured);
        }

        if (! is_array($configured)) {
            return false;
        }

        foreach ($configured as $allowedHost) {
            if (! is_string($allowedHost)) {
                continue;
            }

            $allowedHost = strtolower(trim($allowedHost));
            $allowedHost = preg_replace('#^https?://#', '', $allowedHost) ?? $allowedHost;
            $allowedHost = trim(explode('/', $allowedHost)[0]);

            if ($allowedHost !== '' && $host === $allowedHost) {
                return true;
            }
        }

        return false;
    }

    private function ipv4InRange(int $ip, string $network, int $bits): bool
    {
        $net = ip2long($network);
        if ($net === false) {
            return false;
        }

        $mask = -1 << (32 - $bits);

        return ($ip & $mask) === ($net & $mask);
    }
}
