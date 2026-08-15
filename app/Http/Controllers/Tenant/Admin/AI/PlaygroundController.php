<?php

namespace App\Http\Controllers\Tenant\Admin\AI;

use App\Exceptions\AI\AIProviderException;
use App\Http\Controllers\Controller;
use App\Models\AI\Agent;
use App\Services\AI\TenantAgentRuntime;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlaygroundController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user('tenant')->can('ai.playground.use'), 403);
        return $this->page();
    }

    public function run(Request $request, TenantAgentRuntime $runtime): Response
    {
        abort_unless($request->user('tenant')->can('ai.playground.use'), 403);
        $validated = $request->validate(['agent_uuid' => ['required','uuid'], 'message' => ['required','string','max:'.config('ai.safety.max_input_characters')],
            'images' => ['sometimes','array','max:4'], 'images.*' => ['file','image','mimes:jpg,jpeg,png,gif,webp','max:5120']]);
        $agent = Agent::query()->where('public_uuid', $validated['agent_uuid'])->firstOrFail();
        try {
            return $this->page($runtime->chat($agent, $validated['message'], $request->user('tenant'), media: $this->media($request)));
        } catch (AIProviderException $exception) {
            return $this->page(error: $exception->getMessage());
        }
    }

    public function stream(Request $request, TenantAgentRuntime $runtime): StreamedResponse
    {
        abort_unless($request->user('tenant')->can('ai.playground.use'), 403);
        $validated = $request->validate([
            'agent_uuid' => ['required','uuid'], 'message' => ['required','string','max:'.config('ai.safety.max_input_characters')],
            'stream_id' => ['required','uuid'], 'images' => ['sometimes','array','max:4'],
            'images.*' => ['file','image','mimes:jpg,jpeg,png,gif,webp','max:5120'],
        ]);
        $agent = Agent::query()->where('public_uuid', $validated['agent_uuid'])->firstOrFail();
        $actor = $request->user('tenant'); $media = $this->media($request); $streamId = $validated['stream_id'];

        return response()->stream(function () use ($runtime, $agent, $actor, $validated, $streamId, $media): void {
            ignore_user_abort(true);
            $emit = function (string $event, array $payload): void {
                echo 'event: '.$event."\n".'data: '.json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n\n";
                if (ob_get_level() > 0) @ob_flush();
                flush();
            };
            try {
                $runtime->stream($agent, $validated['message'], $actor, $streamId, $emit, $media);
            } catch (\Throwable) {
                // TenantAgentRuntime already emitted and persisted the safe failure.
            }
        }, 200, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no', 'Connection' => 'keep-alive']);
    }

    public function cancel(Request $request, TenantAgentRuntime $runtime): \Illuminate\Http\JsonResponse
    {
        abort_unless($request->user('tenant')->can('ai.playground.use'), 403);
        $data = $request->validate(['stream_id' => ['required','uuid']]);
        $runtime->cancelStream($data['stream_id']);
        return response()->json(['cancelled' => true]);
    }

    private function page(?array $result = null, ?string $error = null): Response
    {
        return Inertia::render('Tenant/Admin/AI/Playground', [
            'agents' => Agent::query()->with('providerConfig:id,name,status')->orderBy('name')->get()->map(fn (Agent $agent) => [
                'public_uuid' => $agent->public_uuid, 'name' => $agent->name, 'status' => $agent->status->value,
                'provider' => $agent->providerConfig?->name, 'provider_status' => $agent->providerConfig?->status->value,
                'require_citations' => $agent->require_citations,
            ]),
            'result' => $result, 'runtimeError' => $error,
        ]);
    }

    /** @return array<int,array{content:string,mime:string,sha256:string}> */
    private function media(Request $request): array
    {
        return collect($request->file('images', []))->map(function ($file): array {
            $bytes = file_get_contents($file->getRealPath());
            return ['content' => base64_encode($bytes), 'mime' => (string) $file->getMimeType(), 'sha256' => hash('sha256', $bytes)];
        })->all();
    }
}
