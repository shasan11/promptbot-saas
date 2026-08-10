<?php

namespace App\Services\AI;

use App\Models\AI\Prompt;
use App\Models\AI\PromptVersion;
use App\Models\User;
use App\Services\Tenant\TenantAuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PromptService
{
    public function __construct(private readonly TenantAuditLogService $audit) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): Prompt
    {
        $prompt = Prompt::query()->create($data + ['key' => $this->key($data['name']), 'status' => 'draft', 'created_by' => $actor->id, 'updated_by' => $actor->id]);
        $this->audit->record('ai.prompt_created', $actor, 'Created AI prompt', $prompt, newValues: $prompt->only(['name','key','type','variables']), subjectLabel: $prompt->name);
        return $prompt;
    }

    /** @param array<string, mixed> $data */
    public function update(Prompt $prompt, array $data, User $actor): void
    {
        $before = $prompt->only(['name','type','description','template','variables','status']);
        $prompt->fill($data + ['status' => 'draft', 'updated_by' => $actor->id]); $prompt->increment('draft_version'); $prompt->save();
        $this->audit->record('ai.prompt_updated', $actor, 'Updated AI prompt draft', $prompt, oldValues: $before, newValues: $prompt->only(array_keys($before)), subjectLabel: $prompt->name);
    }

    public function publish(Prompt $prompt, User $actor): PromptVersion
    {
        return DB::transaction(function () use ($prompt, $actor) {
            $version = PromptVersion::query()->create(['prompt_id' => $prompt->id, 'version' => $prompt->versions()->max('version') + 1, 'template' => $prompt->template, 'configuration' => ['variables' => $prompt->variables ?? []], 'created_by' => $actor->id]);
            $prompt->forceFill(['active_version_id' => $version->id, 'status' => 'active', 'updated_by' => $actor->id])->save();
            $this->audit->record('ai.prompt_published', $actor, "Published prompt version {$version->version}", $prompt, newValues: ['version' => $version->version], subjectLabel: $prompt->name);
            return $version;
        });
    }

    /** @param array<string, scalar|null> $variables */
    public function render(Prompt $prompt, array $variables): string
    {
        $allowed = array_map('strval', $prompt->variables ?? []); $unknown = array_diff(array_keys($variables), $allowed);
        if ($unknown !== []) throw ValidationException::withMessages(['variables' => 'Unknown prompt variables: '.implode(', ', $unknown)]);
        return preg_replace_callback('/\{\{\s*([a-zA-Z][a-zA-Z0-9_]*)\s*\}\}/', fn ($match) => array_key_exists($match[1], $variables) ? (string) ($variables[$match[1]] ?? '') : $match[0], $prompt->template);
    }

    private function key(string $name): string
    {
        $base = Str::slug($name, '_') ?: 'prompt'; $key = $base; $i = 2;
        while (Prompt::query()->where('key', $key)->exists()) $key = $base.'_'.$i++;
        return $key;
    }
}
