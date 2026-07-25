<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Platform\AuditLogService;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(private readonly PlatformSettingsService $settings) {}

    public function edit(?string $group = 'general'): Response
    {
        $group ??= 'general';

        return Inertia::render('Admin/Settings/Edit', [
            'group' => $group,
            'settings' => $this->settings->getGroup($group),
            'groups' => ['general', 'registration', 'subscriptions', 'email', 'storage', 'queue', 'security', 'maintenance'],
        ]);
    }

    public function update(Request $request, string $group): RedirectResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $oldValues = $this->settings->getGroup($group);
        $this->settings->setGroup($group, $validated['settings']);
        app(AuditLogService::class)->record('settings.updated', null, [
            'old_values' => $oldValues,
            'new_values' => $this->settings->maskSensitive($validated['settings']),
            'reason' => $validated['reason'] ?? null,
            'severity' => $group === 'security' ? 'warning' : 'info',
        ]);

        return back()->with('status', 'Settings updated.');
    }
}
