<?php

namespace App\Services\Knowledge\Crawler;

use App\Exceptions\Knowledge\UnsafeUrlException;

/**
 * SSRF defence for the website crawler.
 *
 * The crawler fetches URLs a tenant supplies, from inside our network. Without
 * this, "add a website source" is a request to make our servers issue arbitrary
 * HTTP requests on the tenant's behalf — reaching cloud metadata endpoints
 * (169.254.169.254 hands out IAM credentials), internal admin panels, and
 * anything else reachable from the worker but not from the internet.
 *
 * Two properties matter:
 *
 *  1. Validation is on the *resolved IP*, not the hostname. `evil.test` with an
 *     A record pointing at 127.0.0.1 passes any name-based check.
 *  2. Validation must be repeated after every redirect. A public URL that 302s
 *     to http://169.254.169.254/ defeats a check performed only on the original
 *     URL — which is why the crawler follows redirects manually, one hop at a
 *     time, rather than letting the HTTP client do it.
 *
 * DNS rebinding (a record that resolves differently between check and fetch)
 * is not fully closed here: doing so requires pinning the connection to the
 * validated IP, which the HTTP client does not expose. The residual window is
 * documented rather than pretended away, and `allow_private_networks` exists so
 * an operator who genuinely needs to crawl an intranet opts in explicitly.
 */
class UrlSafetyGuard
{
    /** @throws UnsafeUrlException */
    public function assertSafe(string $url): void
    {
        $parts = parse_url($url);

        if ($parts === false || empty($parts['host'])) {
            throw UnsafeUrlException::blocked($url, 'malformed URL');
        }

        $scheme = strtolower($parts['scheme'] ?? '');

        // file://, gopher://, dict:// and friends are all useful for reaching
        // things HTTP cannot; none of them are useful for crawling a website.
        if (! in_array($scheme, (array) config('knowledge.crawler.security.allowed_schemes'), true)) {
            throw UnsafeUrlException::blocked($url, "scheme [{$scheme}] is not allowed");
        }

        // Credentials in a crawl URL would be sent to whatever the host
        // resolves to, and are a common way to confuse URL parsers.
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw UnsafeUrlException::blocked($url, 'URLs with embedded credentials are not allowed');
        }

        $host = strtolower(trim($parts['host'], '[]'));

        if (in_array($host, (array) config('knowledge.crawler.security.blocked_hosts'), true)) {
            throw UnsafeUrlException::blocked($url, 'host is on the block list');
        }

        // A bare hostname with no dot is an internal name by definition.
        if (! str_contains($host, '.') && ! str_contains($host, ':')) {
            throw UnsafeUrlException::blocked($url, 'host is not a public domain name');
        }

        if (config('knowledge.crawler.security.allow_private_networks')) {
            return;
        }

        foreach ($this->resolve($host) as $ip) {
            if ($this->isBlockedAddress($ip)) {
                // The offending IP is deliberately not echoed back: reporting
                // it would turn crawl-source validation into an internal network
                // scanner with a friendly UI.
                throw UnsafeUrlException::blocked($url, 'host resolves to a non-public address');
            }
        }
    }

    public function isSafe(string $url): bool
    {
        try {
            $this->assertSafe($url);

            return true;
        } catch (UnsafeUrlException) {
            return false;
        }
    }

    /**
     * Every address the host resolves to. All of them must pass — a host with
     * both a public A record and a private AAAA record is not safe.
     *
     * @return array<int, string>
     */
    private function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $addresses = [];

        foreach (['A' => DNS_A, 'AAAA' => DNS_AAAA] as $type => $constant) {
            $records = @dns_get_record($host, $constant) ?: [];

            foreach ($records as $record) {
                $address = $record['ip'] ?? $record['ipv6'] ?? null;

                if ($address) {
                    $addresses[] = $address;
                }
            }
        }

        if (! $addresses) {
            $resolved = @gethostbyname($host);

            if ($resolved && $resolved !== $host) {
                $addresses[] = $resolved;
            }
        }

        // A host that resolves to nothing is refused rather than allowed
        // through: fail closed, not open.
        if (! $addresses) {
            throw UnsafeUrlException::blocked($host, 'host could not be resolved');
        }

        return $addresses;
    }

    public function isBlockedAddress(string $ip): bool
    {
        // PHP's own reserved/private range filter catches the common cases
        // across both address families.
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return true;
        }

        foreach ((array) config('knowledge.crawler.security.blocked_cidrs') as $cidr) {
            if ($this->inCidr($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    public function inCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = array_pad(explode('/', $cidr, 2), 2, null);
        $bits = (int) $bits;

        $ipBinary = @inet_pton($ip);
        $subnetBinary = @inet_pton($subnet);

        // Comparing an IPv4 address against an IPv6 range (or vice versa) is
        // meaningless; inet_pton returns different lengths for the two families.
        if ($ipBinary === false || $subnetBinary === false || strlen($ipBinary) !== strlen($subnetBinary)) {
            return false;
        }

        $wholeBytes = intdiv($bits, 8);
        $remainingBits = $bits % 8;

        if ($wholeBytes > 0 && substr($ipBinary, 0, $wholeBytes) !== substr($subnetBinary, 0, $wholeBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = ~((1 << (8 - $remainingBits)) - 1) & 0xFF;

        return (ord($ipBinary[$wholeBytes]) & $mask) === (ord($subnetBinary[$wholeBytes]) & $mask);
    }
}
