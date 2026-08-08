<?php

namespace App\Services\Connections;

use InvalidArgumentException;

class DatabaseSafetyService
{
    private const BLOCKED_HOSTS = ['localhost', 'localhost.localdomain', 'metadata.google.internal'];

    private const BLOCKED_IPS = ['169.254.169.254', '100.100.100.200'];

    public function validateDestination(string $host): void
    {
        $normalized = strtolower(trim($host));

        if ($normalized === '' || in_array($normalized, self::BLOCKED_HOSTS, true)) {
            throw new InvalidArgumentException('This database host is not allowed.');
        }

        $ip = filter_var($normalized, FILTER_VALIDATE_IP) ? $normalized : gethostbyname($normalized);

        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException('PromptBot could not resolve this database host.');
        }

        if (in_array($ip, self::BLOCKED_IPS, true) || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new InvalidArgumentException('Private, loopback, reserved, and metadata network destinations are blocked.');
        }
    }

    public function validateSelectOnlyQuery(string $query): void
    {
        $sql = trim($query);

        if (! preg_match('/^select\s/i', $sql)) {
            throw new InvalidArgumentException('Only SELECT queries are allowed.');
        }

        if (str_contains($sql, ';') || preg_match('/\b(insert|update|delete|drop|alter|create|truncate|grant|revoke|merge|call|execute)\b/i', $sql)) {
            throw new InvalidArgumentException('Only a single safe SELECT statement is allowed.');
        }
    }
}
