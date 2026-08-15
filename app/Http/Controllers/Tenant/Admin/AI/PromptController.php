<?php

namespace App\Http\Controllers\Tenant\Admin\AI;

use App\Http\Controllers\Controller;
use App\Models\AI\Prompt;
use App\Services\AI\PromptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PromptController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user('tenant')->can('ai.prompts.view'), 403);
        return Inertia::render('Tenant/Admin/AI/Prompts/Index', [
            'prompts' => Prompt::query()->with('activeVersion:id,public_uuid,version')->latest()->get()->map(fn (Prompt $prompt) => [
                'public_uuid' => $prompt->public_uuid, 'name' => $prompt->name, 'key' => $prompt->key, 'type' => $prompt->type,
                'description' => $prompt->description, 'status' => $prompt->status, 'template' => $prompt->template,
                'variables' => $prompt->variables ?? [], 'active_version' => $prompt->activeVersion?->version, 'updated_at' => $prompt->updated_at,
            ]), 'canManage' => $request->user('tenant')->can('ai.prompts.manage'),
        ]);
    }

    public function store(Request $request, PromptService $service): RedirectResponse
    {
        abort_unless($request->user('tenant')->can('ai.prompts.manage'), 403);
        $service->create($this->validated($request), $request->user('tenant'));
        return back()->with('status', 'Prompt draft created.');
    }

    public function update(Request $request, Prompt $prompt, PromptService $service): RedirectResponse
    {
        abort_unless($request->user('tenant')->can('ai.prompts.manage'), 403);
        $service->update($prompt, $this->validated($request), $request->user('tenant'));
        return back()->with('status', 'Prompt draft updated.');
    }

    public function publish(Request $request, Prompt $prompt, PromptService $service): RedirectResponse
    {
        abort_unless($request->user('tenant')->can('ai.prompts.manage'), 403);
        $version = $service->publish($prompt, $request->user('tenant'));
        return back()->with('status', "Prompt version {$version->version} published.");
    }

    private function validated(Request $request): array
    {
        return $request->validate(['name' => ['required','string','max:120'], 'type' => ['required','string','in:system,task,classification,summary,draft,tool'], 'description' => ['nullable','string','max:2000'], 'template' => ['required','string','min:10','max:50000'], 'variables' => ['array','max:30'], 'variables.*' => ['string','regex:/^[a-zA-Z][a-zA-Z0-9_]*$/','distinct']]);
    }
}
