<?php

namespace App\Http\Controllers\Tenant\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AI\Agent;
use App\Services\AI\TenantAgentRuntime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AIAgentController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Agent::query()->where('status', 'active')->get()->map(fn (Agent $agent) => ['id' => $agent->public_uuid, 'key' => $agent->agent_key, 'name' => $agent->name, 'deployment_mode' => $agent->deployment_mode->value])]);
    }

    public function run(Request $request, Agent $agent, TenantAgentRuntime $runtime): JsonResponse
    {
        abort_unless($agent->isDeployed(), 422, 'The selected agent is not deployed.');
        $data = $request->validate(['message' => ['required','string','max:'.config('ai.safety.max_input_characters')]]);
        $idempotencyKey = trim((string) $request->header('Idempotency-Key'));
        abort_if($idempotencyKey === '' || strlen($idempotencyKey) > 200, 422, 'A valid Idempotency-Key header is required.');
        return response()->json(['data' => $runtime->chat($agent, $data['message'], feature: 'api', operation: 'chat', idempotencyKey: $idempotencyKey)]);
    }
}
