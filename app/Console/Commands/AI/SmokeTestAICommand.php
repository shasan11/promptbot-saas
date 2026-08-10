<?php

namespace App\Console\Commands\AI;

use App\Models\AI\Agent;
use App\Models\User;
use App\Services\AI\TenantAgentRuntime;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SmokeTestAICommand extends Command
{
    protected $signature = 'ai:smoke {--agent= : Agent key or UUID} {--message=What is the documented refund period?} {--stream : Exercise Neuron streaming} {--vision : Send a generated one-pixel PNG as multimodal input}';
    protected $description = 'Run one real, grounded AI request in the initialized tenant context';

    public function handle(TenantAgentRuntime $runtime): int
    {
        if (! tenancy()->initialized) {
            $this->error('Initialize a tenant first, for example with tenants:run ai:smoke --tenants=acme.');
            return self::FAILURE;
        }
        $selector = trim((string) $this->option('agent'));
        $agent = Agent::query()->where('status', 'active')->when($selector !== '', fn ($query) => $query->where(fn ($query) => $query->where('agent_key', $selector)->orWhere('public_uuid', $selector)))->first();
        if (! $agent) {
            $this->error('No matching deployed AI agent exists.');
            return self::FAILURE;
        }
        $media = $this->option('vision') ? [[
            'content' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z8S8AAAAASUVORK5CYII=',
            'mime' => 'image/png', 'sha256' => hash('sha256', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z8S8AAAAASUVORK5CYII=')),
        ]] : [];
        if ($this->option('stream')) {
            $chunks = 0;
            $result = $runtime->stream($agent, (string) $this->option('message'), User::query()->orderBy('id')->first(), (string) Str::uuid(),
                function (string $event) use (&$chunks): void { if ($event === 'text') $chunks++; }, $media);
            $this->line("Streamed text chunks: {$chunks}");
        } else {
            $result = $runtime->chat($agent, (string) $this->option('message'), User::query()->orderBy('id')->first(), 'smoke_test', $this->option('vision') ? 'vision' : 'grounded_chat', media: $media);
        }
        $this->info('Run '.$result['run_uuid'].' completed in '.$result['latency_ms'].' ms.');
        $this->line('Citations: '.count($result['citations'] ?? []));
        $this->line(mb_strimwidth((string) $result['text'], 0, 500, '…'));
        return self::SUCCESS;
    }
}
