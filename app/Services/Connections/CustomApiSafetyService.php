<?php

namespace App\Services\Connections;

use InvalidArgumentException;

class CustomApiSafetyService
{
    private const ALLOWED_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    private const BLOCKED_HOSTS = [
        'localhost',
        'localhost.localdomain',
        'metadata.google.internal',
        'instance-data',
    ];

    private const BLOCKED_IPS = [
        '0.0.0.0',
        '169.254.169.254',
        '100.100.100.200',
    ];

    public function validateBaseUrl(string $url): void
    {
        $parts = parse_url(trim($url));

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new InvalidArgumentException('A custom API base URL must include a scheme and host.');
        }

        if (! in_array(strtolower($parts['scheme']), ['https'], true)) {
            throw new InvalidArgumentException('Custom API base URLs must use HTTPS.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('Credentials are not allowed in custom API URLs.');
        }

        if (isset($parts['query']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException('Custom API base URLs cannot include query strings or fragments.');
        }

        $this->validateHost($parts['host']);
    }

    public function validateOperation(string $method, string $path, string $riskLevel = 'low'): void
    {
        $method = strtoupper(trim($method));
        $path = trim($path);

        if (! in_array($method, self::ALLOWED_METHODS, true)) {
            throw new InvalidArgumentException('This HTTP method is not allowed for custom API operations.');
        }

        if ($method === 'DELETE' && ! in_array($riskLevel, ['high', 'critical'], true)) {
            throw new InvalidArgumentException('DELETE operations must be explicitly marked high or critical risk.');
        }

        if ($path === '' || ! str_starts_with($path, '/')) {
            throw new InvalidArgumentException('Custom API operation paths must start with /.');
        }

        if (str_starts_with($path, '//') || preg_match('#^[a-z][a-z0-9+.-]*://#i', $path)) {
            throw new InvalidArgumentException('Custom API operation paths must be relative to the connection base URL.');
        }

        if (preg_match('/[\r\n]/', $path) || str_contains($path, '..')) {
            throw new InvalidArgumentException('Custom API operation paths cannot contain traversal or control characters.');
        }
    }

    public function validateHeaders(array $headers): void
    {
        foreach ($headers as $name => $value) {
            if (! is_string($name) || trim($name) === '' || preg_match('/[\r\n:]/', $name)) {
                throw new InvalidArgumentException('Custom API header names are invalid.');
            }

            if (is_string($value) && preg_match('/[\r\n]/', $value)) {
                throw new InvalidArgumentException('Custom API header values cannot contain control characters.');
            }
        }
    }

    private function validateHost(string $host): void
    {
        $normalized = strtolower(trim($host, " \t\n\r\0\x0B[]"));

        if ($normalized === '' || in_array($normalized, self::BLOCKED_HOSTS, true)) {
            throw new InvalidArgumentException('This custom API host is not allowed.');
        }

        if (str_ends_with($normalized, '.localhost') || str_ends_with($normalized, '.local')) {
            throw new InvalidArgumentException('Local custom API hosts are blocked.');
        }

        $ips = filter_var($normalized, FILTER_VALIDATE_IP)
            ? [$normalized]
            : $this->resolveHost($normalized);

        foreach ($ips as $ip) {
            if ($this->isBlockedIp($ip)) {
                throw new InvalidArgumentException('Private, loopback, reserved, and metadata network destinations are blocked.');
            }
        }
    }

    /** @return array<int, string> */
    private function resolveHost(string $host): array
    {
        $ips = gethostbynamel($host);

        if (! is_array($ips) || $ips === []) {
            throw new InvalidArgumentException('PromptBot could not resolve this custom API host.');
        }

        return $ips;
    }

    private function isBlockedIp(string $ip): bool
    {
        return in_array($ip, self::BLOCKED_IPS, true)
            || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}
