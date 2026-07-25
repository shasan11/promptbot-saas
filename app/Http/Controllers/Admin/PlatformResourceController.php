<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RendersResourceTable;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Response;

class PlatformResourceController extends Controller
{
    use RendersResourceTable;

    public function __invoke(Request $request, string $resource): Response
    {
        $map = [
            'usage' => ['Usage Metering', 'usage_metrics', ['tenant_id', 'metric', 'quantity', 'period_start', 'period_end']],
            'integrations' => ['Integrations', 'integration_definitions', ['name', 'provider', 'status']],
            'ai-models' => ['AI Models', 'ai_model_configs', ['provider', 'model', 'purpose', 'is_active']],
            'provider-health' => ['Provider Health', 'provider_health_logs', ['provider', 'status', 'latency_ms', 'message', 'checked_at']],
            'feature-flags' => ['Feature Flags', 'features', ['name', 'code', 'type']],
        ];

        abort_unless(isset($map[$resource]), 404);
        [$title, $table, $keys] = $map[$resource];

        return $this->tablePage($request, $title, $table, $this->columns($keys));
    }

    private function columns(array $keys): array
    {
        return collect($keys)->map(fn (string $key) => ['key' => $key, 'label' => str($key)->headline()->toString(), 'searchable' => true])->all();
    }
}
