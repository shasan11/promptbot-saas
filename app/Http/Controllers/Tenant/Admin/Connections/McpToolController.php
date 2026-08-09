<?php

namespace App\Http\Controllers\Tenant\Admin\Connections;

use App\Http\Controllers\Controller;
use App\Models\Connections\Connection;
use App\Models\Connections\ConnectionAction;
use App\Services\Connections\McpSafetyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class McpToolController extends Controller
{
    public function store(Request $request, Connection $connection, McpSafetyService $service): RedirectResponse
    {
        abort_unless($request->user('tenant')?->can('connections.mcp.manage'), 403);

        $data = $request->validate([
            'key' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'risk_level' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'input_schema' => ['nullable', 'array'],
            'output_schema' => ['nullable', 'array'],
            'capabilities' => ['nullable', 'array'],
            'discovery_source' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $service->registerTool($connection, $data, $request->user('tenant'));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['key' => $exception->getMessage()])->withInput();
        }

        return back()->with('status', 'MCP tool discovered. Review and enable it explicitly before use.');
    }

    public function update(Request $request, Connection $connection, ConnectionAction $tool, McpSafetyService $service): RedirectResponse
    {
        abort_unless($request->user('tenant')?->can('connections.mcp.manage'), 403);

        $data = $request->validate([
            'enabled_for_ai' => ['required', 'boolean'],
            'enabled_for_workflows' => ['required', 'boolean'],
            'requires_approval' => ['required', 'boolean'],
            'status' => ['nullable', Rule::in(['active', 'disabled'])],
        ]);

        try {
            $service->updateToolPolicy($connection, $tool, $data, $request->user('tenant'));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['enabled_for_ai' => $exception->getMessage()]);
        }

        return back()->with('status', 'MCP tool policy updated.');
    }
}
