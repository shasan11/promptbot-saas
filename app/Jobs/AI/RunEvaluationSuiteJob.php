<?php

namespace App\Jobs\AI;

use App\Jobs\Concerns\TenantAware;
use App\Models\AI\EvaluationResult;
use App\Models\AI\EvaluationRun;
use App\Services\AI\TenantAgentRuntime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

class RunEvaluationSuiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TenantAware;

    public int $tries = 1;
    public int $timeout = 1800;

    public function __construct(private readonly int $evaluationRunId)
    {
        $this->captureTenant(); $this->onQueue(config('ai.queues.evaluation'));
    }

    public function handle(TenantAgentRuntime $runtime): void
    {
        $run = EvaluationRun::query()->with(['suite.cases','agent'])->find($this->evaluationRunId);
        if (! $run || ! $run->agent) return;
        $cases = $run->suite->cases->where('active', true);
        $run->forceFill(['status' => 'running', 'started_at' => now(), 'total_cases' => $cases->count()])->save();
        $passed = 0;
        foreach ($cases as $case) {
            $started = hrtime(true);
            try {
                $output = $runtime->chat($run->agent, $case->input, feature: 'evaluation', operation: $case->category);
                $assertions = $this->assertions($output, (array) $case->assertions);
                $ok = ! in_array(false, array_column($assertions, 'passed'), true); if ($ok) $passed++;
                $aiRunId = \App\Models\AI\Run::query()->where('public_uuid', $output['run_uuid'])->value('id');
                EvaluationResult::query()->create(['evaluation_run_id' => $run->id, 'evaluation_case_id' => $case->id, 'ai_run_id' => $aiRunId, 'status' => $ok ? 'passed' : 'failed', 'assertion_results' => $assertions, 'output_excerpt' => Str::limit($output['text'], 4000), 'latency_ms' => $output['latency_ms']]);
            } catch (Throwable $exception) {
                EvaluationResult::query()->create(['evaluation_run_id' => $run->id, 'evaluation_case_id' => $case->id, 'status' => 'error', 'error_message_safe' => 'The evaluation case could not be completed.', 'latency_ms' => (int) round((hrtime(true) - $started) / 1_000_000)]);
            }
        }
        $total = $cases->count();
        $run->forceFill(['status' => 'completed', 'passed_cases' => $passed, 'failed_cases' => $total - $passed, 'pass_rate' => $total ? round($passed / $total * 100, 3) : null, 'finished_at' => now()])->save();
    }

    /** @param array<string, mixed> $output
     *  @param array<int, array<string, mixed>> $rules
     *  @return array<int, array<string, mixed>>
     */
    private function assertions(array $output, array $rules): array
    {
        return collect($rules)->map(function (array $rule) use ($output) {
            $type = $rule['type'] ?? ''; $value = (string) ($rule['value'] ?? '');
            $passed = match ($type) {
                'contains' => str_contains(mb_strtolower($output['text']), mb_strtolower($value)),
                'not_contains' => ! str_contains(mb_strtolower($output['text']), mb_strtolower($value)),
                'regex' => @preg_match($value, $output['text']) === 1,
                'citations_required' => count($output['citations'] ?? []) > 0,
                'max_latency_ms' => $output['latency_ms'] <= (int) $value,
                default => false,
            };
            return ['type' => $type, 'value' => $value, 'passed' => $passed];
        })->all();
    }
}
