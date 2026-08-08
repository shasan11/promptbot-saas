<?php

namespace App\Http\Controllers\Tenant\Admin\Knowledge;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Admin\Knowledge\Concerns\ResolvesKnowledgeScope;
use App\Services\Knowledge\Embedding\EmbeddingProviderFactory;
use App\Services\Knowledge\KnowledgeLimitService;
use App\Services\Tenant\TenantAuditLogService;
use App\Services\Tenant\TenantSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Workspace-wide knowledge settings.
 *
 * Everything here is a *default* for new knowledge bases plus a few operational
 * switches. Values are clamped to the platform limits in config/knowledge.php on
 * save — a tenant setting can never exceed what the platform allows, or one
 * workspace could configure itself into monopolising shared queue workers.
 */
class KnowledgeSettingsController extends Controller
{
    use ResolvesKnowledgeScope;

    private const PREFIX = 'knowledge.';

    public function __construct(
        private readonly TenantSettingsService $settings,
        private readonly TenantAuditLogService $auditLog,
    ) {}

    public function edit(EmbeddingProviderFactory $providers, KnowledgeLimitService $limits): Response
    {
        abort_unless($this->actor()->can('knowledge.settings.manage'), 403);

        return Inertia::render('Tenant/Admin/Knowledge/Settings/Index', [
            'settings' => $this->currentSettings(),
            'embeddingProviders' => $providers->catalogue(),
            'usage' => $limits->usage(),
            'platformLimits' => [
                'max_file_size_kb' => config('knowledge.uploads.max_file_size_kb'),
                'chunk_size' => [
                    'min' => config('knowledge.chunking.min_chunk_size'),
                    'max' => config('knowledge.chunking.max_chunk_size'),
                ],
                'max_top_k' => config('knowledge.retrieval.max_top_k'),
                'max_context_tokens' => config('knowledge.retrieval.max_context_tokens'),
                'crawl_max_pages' => config('knowledge.crawler.max_pages_limit'),
            ],
            'ocr' => [
                'enabled' => (bool) config('knowledge.extraction.ocr.enabled'),
                'provider' => config('knowledge.extraction.ocr.provider'),
                // Reported honestly: the shipped provider is a null object, and
                // the settings screen says so rather than offering a toggle that
                // does nothing.
                'available' => app(\App\Contracts\Knowledge\OcrProviderInterface::class)->isAvailable(),
            ],
            'vectorStore' => [
                'driver' => app(\App\Contracts\Knowledge\VectorStoreInterface::class)->name(),
                'max_candidates' => config('knowledge.vector_store.max_candidates'),
            ],
            'languages' => config('knowledge.languages'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($this->actor()->can('knowledge.settings.manage'), 403);

        $validated = $request->validate([
            'default_language' => ['required', 'string', 'max:12'],
            'default_embedding_provider' => ['required', 'string', 'in:'.implode(',', array_keys((array) config('knowledge.embeddings.providers')))],
            'default_chunk_size' => ['required', 'integer'],
            'default_chunk_overlap' => ['required', 'integer', 'min:0'],
            'default_top_k' => ['required', 'integer', 'min:1'],
            'default_similarity_threshold' => ['required', 'numeric', 'min:0', 'max:1'],
            'max_file_size_kb' => ['required', 'integer', 'min:64'],
            'auto_retry_failed_sources' => ['required', 'boolean'],
            'notify_on_source_failure' => ['required', 'boolean'],
            'default_review_every_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        $before = $this->currentSettings();
        $clamped = $this->clampToPlatformLimits($validated);

        foreach ($clamped as $key => $value) {
            $this->settings->set(self::PREFIX.$key, $value);
        }

        $this->auditLog->record(
            'knowledge.settings_updated',
            $request->user('tenant'),
            'Updated knowledge settings',
            oldValues: $before,
            newValues: $clamped,
            subjectType: 'knowledge_settings',
            subjectLabel: 'Knowledge settings',
        );

        $adjusted = array_keys(array_diff_assoc(
            array_map(fn ($v) => (string) $v, $validated),
            array_map(fn ($v) => (string) $v, $clamped)
        ));

        return back()->with('status', $adjusted
            ? 'Settings saved. Some values were adjusted to the platform maximum: '.implode(', ', $adjusted).'.'
            : 'Knowledge settings saved.');
    }

    /** @return array<string, mixed> */
    private function currentSettings(): array
    {
        return [
            'default_language' => $this->settings->get(self::PREFIX.'default_language', 'en'),
            'default_embedding_provider' => $this->settings->get(self::PREFIX.'default_embedding_provider', config('knowledge.embeddings.default_provider')),
            'default_chunk_size' => (int) $this->settings->get(self::PREFIX.'default_chunk_size', config('knowledge.chunking.default_chunk_size')),
            'default_chunk_overlap' => (int) $this->settings->get(self::PREFIX.'default_chunk_overlap', config('knowledge.chunking.default_chunk_overlap')),
            'default_top_k' => (int) $this->settings->get(self::PREFIX.'default_top_k', config('knowledge.retrieval.default_top_k')),
            'default_similarity_threshold' => (float) $this->settings->get(self::PREFIX.'default_similarity_threshold', config('knowledge.retrieval.default_similarity_threshold')),
            'max_file_size_kb' => (int) $this->settings->get(self::PREFIX.'max_file_size_kb', config('knowledge.uploads.max_file_size_kb')),
            'auto_retry_failed_sources' => (bool) $this->settings->get(self::PREFIX.'auto_retry_failed_sources', true),
            'notify_on_source_failure' => (bool) $this->settings->get(self::PREFIX.'notify_on_source_failure', true),
            'default_review_every_days' => $this->settings->get(self::PREFIX.'default_review_every_days', config('knowledge.freshness.default_review_every_days')),
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function clampToPlatformLimits(array $values): array
    {
        $values['default_chunk_size'] = max(
            (int) config('knowledge.chunking.min_chunk_size'),
            min((int) config('knowledge.chunking.max_chunk_size'), (int) $values['default_chunk_size'])
        );

        // Overlap at or above chunk size would stop the chunker advancing, so
        // it is capped by ratio rather than merely validated against the
        // submitted (possibly also clamped) chunk size.
        $values['default_chunk_overlap'] = max(0, min(
            (int) floor($values['default_chunk_size'] * (float) config('knowledge.chunking.max_overlap_ratio')),
            (int) $values['default_chunk_overlap']
        ));

        $values['default_top_k'] = min((int) config('knowledge.retrieval.max_top_k'), (int) $values['default_top_k']);
        $values['max_file_size_kb'] = min((int) config('knowledge.uploads.max_file_size_kb'), (int) $values['max_file_size_kb']);

        return $values;
    }
}
