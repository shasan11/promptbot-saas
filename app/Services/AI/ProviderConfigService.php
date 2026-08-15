<?php

namespace App\Services\AI;

use App\Enums\AI\ProviderStatus;
use App\Models\AI\ProviderConfig;
use App\Models\User;
use App\Services\Tenant\TenantAuditLogService;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class ProviderConfigService
{
    public function __construct(private readonly TenantAuditLogService $audit) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): ProviderConfig
    {
        $credentials = $this->credentials($data);
        $this->ensureCanEnable($data, $credentials);

        $provider = ProviderConfig::query()->create([
            ...Arr::except($data, $this->virtualFields()),
            'status' => ProviderStatus::Untested,
            'credentials_encrypted' => $credentials,
            'configuration' => $this->configuration($data),
            'capabilities' => config("ai.providers.{$data['provider']}.capabilities", []),
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $this->audit->record('ai.provider_created', $actor, 'Created AI provider configuration', $provider,
            newValues: $provider->safePayload(), subjectLabel: $provider->name);

        return $provider;
    }

    /** @param array<string, mixed> $data */
    public function update(ProviderConfig $provider, array $data, User $actor): ProviderConfig
    {
        $before = $provider->safePayload();
        $credentials = $this->credentials($data, (array) $provider->credentials_encrypted);
        $this->ensureCanEnable($data, $credentials);

        $provider->fill([
            ...Arr::except($data, $this->virtualFields()),
            'credentials_encrypted' => $credentials,
            'configuration' => $this->configuration($data, (array) $provider->configuration),
            'capabilities' => config("ai.providers.{$data['provider']}.capabilities", []),
            'updated_by' => $actor->id,
        ]);
        if ($provider->isDirty(['provider', 'base_url', 'default_chat_model', 'credentials_encrypted'])) {
            $provider->status = ProviderStatus::Untested;
            $provider->last_test_status = null;
        }
        $provider->save();

        $this->audit->record('ai.provider_updated', $actor, 'Updated AI provider configuration', $provider,
            oldValues: $before, newValues: $provider->safePayload(), subjectLabel: $provider->name);

        return $provider;
    }

    public function delete(ProviderConfig $provider, User $actor): void
    {
        if ($provider->agents()->exists()) {
            throw ValidationException::withMessages(['provider' => 'This provider is assigned to an agent and cannot be deleted.']);
        }
        $before = $provider->safePayload();
        $name = $provider->name;
        $provider->delete();
        $this->audit->record('ai.provider_deleted', $actor, 'Deleted AI provider configuration',
            oldValues: $before, subjectType: 'ai_provider_config', subjectLabel: $name);
    }

    /** @param array<string, mixed> $data
     *  @param array<string, mixed> $existing
     *  @return array<string, mixed>
     */
    private function credentials(array $data, array $existing = []): array
    {
        $key = trim((string) ($data['api_key'] ?? ''));
        if ($key !== '') {
            $existing['api_key'] = $key;
        }
        return $existing;
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function configuration(array $data, array $existing = []): array
    {
        $configuration = $existing;
        $configuration['parameters'] = array_filter([
            'temperature' => isset($data['temperature']) ? (float) $data['temperature'] : null,
            'top_p' => isset($data['top_p']) ? (float) $data['top_p'] : null,
            'max_tokens' => isset($data['max_tokens']) ? (int) $data['max_tokens'] : null,
        ], fn ($value) => $value !== null);

        $model = trim((string) $data['default_chat_model']);
        $input = $data['input_cost_per_million'] ?? null;
        $output = $data['output_cost_per_million'] ?? null;
        if ($input !== null && $output !== null) {
            $configuration['pricing']['models'][$model] = array_filter([
                'currency' => strtoupper((string) ($data['pricing_currency'] ?? 'USD')),
                'input_per_million' => (float) $input,
                'output_per_million' => (float) $output,
                'cached_input_per_million' => isset($data['cached_input_cost_per_million']) ? (float) $data['cached_input_cost_per_million'] : null,
                'reasoning_per_million' => isset($data['reasoning_cost_per_million']) ? (float) $data['reasoning_cost_per_million'] : null,
            ], fn ($value) => $value !== null);
        } else {
            unset($configuration['pricing']['models'][$model]);
        }

        return $configuration;
    }

    /** @return array<int, string> */
    private function virtualFields(): array
    {
        return ['api_key', 'temperature', 'top_p', 'max_tokens', 'pricing_currency',
            'input_cost_per_million', 'output_cost_per_million', 'cached_input_cost_per_million',
            'reasoning_cost_per_million'];
    }

    /** @param array<string, mixed> $data
     *  @param array<string, mixed> $credentials
     */
    private function ensureCanEnable(array $data, array $credentials): void
    {
        $requiresKey = (bool) config("ai.providers.{$data['provider']}.requires_api_key", true);
        if (($data['enabled'] ?? false) && $requiresKey && blank($credentials['api_key'] ?? null)) {
            throw ValidationException::withMessages(['api_key' => 'An API key is required before this provider can be enabled.']);
        }
    }
}
