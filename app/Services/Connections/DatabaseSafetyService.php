<?php

namespace App\Services\Connections;

use InvalidArgumentException;

class DatabaseSafetyService
{
    private const BLOCKED_HOSTS = ['localhost', 'localhost.localdomain', 'metadata.google.internal'];

    private const BLOCKED_IPS = ['169.254.169.254', '100.100.100.200'];

    private const SENSITIVE_COLUMN_PATTERNS = [
        'password',
        'secret',
        'token',
        'private_key',
        'credit_card',
        'card_number',
        'cvv',
        'ssn',
        'national_id',
        'api_key',
    ];

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

    public function validateDataSourceConfig(array $config): array
    {
        $allowed = $this->normalizeColumns($config['allowed_columns'] ?? []);
        $excluded = $this->normalizeColumns($config['excluded_columns'] ?? []);

        if ($allowed === []) {
            throw new InvalidArgumentException('Select at least one allowed column for this database source.');
        }

        $this->validateIdentifier($config['schema_name'] ?? null, 'schema');
        $this->validateIdentifier($config['table_name'] ?? null, 'table');
        $this->validateIdentifier($config['primary_key'] ?? null, 'primary key', required: false);
        $this->validateIdentifier($config['incremental_column'] ?? null, 'incremental column', required: false);

        foreach ([...$allowed, ...$excluded] as $column) {
            $this->validateIdentifier($column, 'column');
        }

        $sensitiveAllowed = array_diff($this->sensitiveColumns($allowed), $excluded);

        if ($sensitiveAllowed !== []) {
            throw new InvalidArgumentException('Sensitive columns must be excluded before this database source can be saved.');
        }

        $rowLimit = (int) ($config['row_limit'] ?? 0);

        if ($rowLimit < 1 || $rowLimit > 100000) {
            throw new InvalidArgumentException('Database row limit must be between 1 and 100000.');
        }

        if (! empty($config['raw_sql'])) {
            $this->validateSelectOnlyQuery((string) $config['raw_sql']);
        }

        return [
            'allowed_columns' => array_values($allowed),
            'excluded_columns' => array_values($excluded),
            'sensitive_columns' => array_values($this->sensitiveColumns($allowed)),
        ];
    }

    /** @return array<int, string> */
    public function sensitiveColumns(array $columns): array
    {
        $normalized = $this->normalizeColumns($columns);

        return array_values(array_filter($normalized, function (string $column): bool {
            $needle = strtolower($column);

            foreach (self::SENSITIVE_COLUMN_PATTERNS as $pattern) {
                if (str_contains($needle, $pattern)) {
                    return true;
                }
            }

            return false;
        }));
    }

    /** @return array<int, string> */
    private function normalizeColumns(array $columns): array
    {
        return collect($columns)
            ->map(fn ($column) => trim((string) $column))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function validateIdentifier(?string $identifier, string $label, bool $required = true): void
    {
        $identifier = trim((string) $identifier);

        if ($identifier === '') {
            if ($required) {
                throw new InvalidArgumentException("Database {$label} is required.");
            }

            return;
        }

        if ($identifier === '*' || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new InvalidArgumentException("Database {$label} contains unsafe characters.");
        }
    }
}
