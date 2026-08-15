<?php

namespace App\Services\AI;

use App\Models\AI\ProviderConfig;
use Illuminate\Validation\ValidationException;

class ProviderCapabilityService
{
    /** @return array<int, string> */
    public function available(ProviderConfig|string $provider): array
    {
        if ($provider instanceof ProviderConfig && is_array($provider->capabilities) && $provider->capabilities !== []) {
            return array_values(array_unique(array_map('strval', $provider->capabilities)));
        }

        $key = $provider instanceof ProviderConfig ? $provider->provider : $provider;

        return array_values((array) config("ai.providers.{$key}.capabilities", []));
    }

    /** @param array<int, string> $required */
    public function ensure(ProviderConfig $provider, array $required): void
    {
        $missing = array_values(array_diff($required, $this->available($provider)));

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'provider_config_id' => 'This provider does not support: '.implode(', ', $missing).'.',
            ]);
        }
    }
}
