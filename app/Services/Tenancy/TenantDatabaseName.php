<?php

namespace App\Services\Tenancy;

use Illuminate\Support\Str;
use InvalidArgumentException;

class TenantDatabaseName
{
    public static function fromSlug(string $slug, ?string $prefix = null): string
    {
        $safeSlug = Str::of($slug)->lower()->replaceMatches('/[^a-z0-9_]+/', '_')->trim('_')->toString();

        if ($safeSlug === '') {
            throw new InvalidArgumentException('Tenant slug cannot produce a safe database name.');
        }

        $name = ($prefix ?? '').'tenant_'.$safeSlug;

        if (strlen($name) > 60) {
            $hash = substr(sha1($name), 0, 10);
            $name = substr($name, 0, 49).'_'.$hash;
        }

        if (! preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            throw new InvalidArgumentException('Generated database name contains unsafe characters.');
        }

        return $name;
    }

    public static function assertSafe(string $name): string
    {
        if (! preg_match('/^[a-zA-Z0-9_]{1,64}$/', $name)) {
            throw new InvalidArgumentException('Database names may only contain letters, numbers and underscores.');
        }

        return $name;
    }
}
