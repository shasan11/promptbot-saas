<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceEntryController extends Controller
{
    public function engagement(Request $request): RedirectResponse
    {
        return $this->firstAllowed($request, ['channels.view' => 'tenant.admin.channels.index', 'experience.view' => 'tenant.admin.experience.index']);
    }

    public function operations(Request $request): RedirectResponse
    {
        return $this->firstAllowed($request, ['operations.view' => 'tenant.admin.operations.index', 'automation.view' => 'tenant.admin.automation.index', 'reports.view' => 'tenant.admin.reports.index', 'quality.view' => 'tenant.admin.quality.index', 'workforce.view' => 'tenant.admin.workforce.index']);
    }

    public function platform(Request $request): RedirectResponse
    {
        return $this->firstAllowed($request, ['connections.view' => 'tenant.admin.connections.overview', 'governance.view' => 'tenant.admin.governance.index']);
    }

    private function firstAllowed(Request $request, array $destinations): RedirectResponse
    {
        $user = $request->user('tenant');
        foreach ($destinations as $permission => $routeName) {
            if ($user->can($permission)) return redirect()->route($routeName);
        }
        abort(403);
    }
}
