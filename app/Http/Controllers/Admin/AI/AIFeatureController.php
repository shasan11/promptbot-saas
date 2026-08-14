<?php

namespace App\Http\Controllers\Admin\AI;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Services\Platform\AuditLogService;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AIFeatureController extends Controller
{
    private function fields(): array
    {
        return [
            'knowledge_embeddings' => [
                'label' => 'Knowledge Embeddings',
                'description' => 'Allows the Knowledge Base to generate embeddings through the configured AI provider instead of the built-in offline provider.',
                'required_purpose' => 'knowledge_embedding',
            ],
            'knowledge_answers' => [
                'label' => 'Knowledge Answers (RAG generation)',
                'description' => 'Lets the Knowledge Retrieval Playground and future chat surfaces generate a real AI answer from retrieved context, instead of only an extractive preview. Requires embeddings to also be enabled.',
                'required_purpose' => 'rag_answer',
            ],
            'inbox_reply_suggestions' => [
                'label' => 'Inbox Reply Suggestions',
                'description' => 'Reserved for a future release — AI-drafted reply suggestions in the Inbox.',
                'required_purpose' => 'inbox_reply_draft',
            ],
            'automation_ai_actions' => [
                'label' => 'Automation AI Actions',
                'description' => 'Reserved for a future release — AI-driven steps inside Automation workflows.',
                'required_purpose' => 'automation_action',
            ],
        ];
    }

    public function edit(PlatformSettingsService $settings): Response
    {
        $current = $settings->group('ai_features');
        $masterEnabled = (bool) ($settings->get('ai', 'enabled', false));

        $features = collect($this->fields())->map(fn (array $field, string $key) => [
            'key' => $key,
            'label' => $field['label'],
            'description' => $field['description'],
            'required_purpose' => $field['required_purpose'],
            'enabled' => (bool) ($current[$key] ?? false),
        ])->values();

        return Inertia::render('Admin/AI/Features', [
            'features' => $features,
            'masterEnabled' => $masterEnabled,
        ]);
    }

    public function update(Request $request, PlatformSettingsService $settings, AuditLogService $auditLog): RedirectResponse
    {
        $keys = array_keys($this->fields());
        $validated = $request->validate(collect($keys)->mapWithKeys(fn ($key) => [$key => ['sometimes', 'boolean']])->all());

        $oldValues = [];
        $newValues = [];

        foreach ($keys as $key) {
            $value = (bool) ($validated[$key] ?? false);
            $setting = PlatformSetting::query()->where('group', 'ai_features')->where('key', $key)->first();
            $oldValues[$key] = (bool) data_get($setting?->value, 'value', false);
            $newValues[$key] = $value;

            PlatformSetting::updateOrCreate(
                ['group' => 'ai_features', 'key' => $key],
                ['id' => $setting?->id ?? (string) Str::uuid(), 'value' => ['value' => $value]]
            );
        }

        $auditLog->record('ai.feature.updated', null, [
            'entity_type' => 'PlatformSetting',
            'entity_id' => 'ai_features',
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);

        $settings->clear()->apply();

        return back()->with('status', 'AI features updated.');
    }
}
