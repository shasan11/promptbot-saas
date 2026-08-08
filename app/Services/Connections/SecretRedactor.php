<?php

namespace App\Services\Connections;

class SecretRedactor
{
    private const SECRET_PATTERN = '/(authorization|api[_-]?key|token|secret|password|cookie|private[_-]?key|client[_-]?secret|access[_-]?key)/i';

    public function redact(mixed $value): mixed
    {
        if (is_array($value)) {
            return collect($value)->mapWithKeys(function (mixed $item, string|int $key) {
                return [$key => preg_match(self::SECRET_PATTERN, (string) $key) ? '[redacted]' : $this->redact($item)];
            })->all();
        }

        if (is_string($value) && preg_match('/(Bearer|Basic)\s+[A-Za-z0-9._~+\/=-]+/i', $value)) {
            return preg_replace('/(Bearer|Basic)\s+[A-Za-z0-9._~+\/=-]+/i', '$1 [redacted]', $value);
        }

        return $value;
    }
}
