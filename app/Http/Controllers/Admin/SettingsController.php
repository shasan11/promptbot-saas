<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Services\Platform\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    /**
     * Fixed, hand-curated setting definitions. Keeping this a plain array
     * (rather than a database-driven definition table) is the deliberate
     * "basic" scope for this build — add a key here to expose a new setting.
     */
    private const DEFINITIONS = [
        'general' => [
            'platform_name' => ['label' => 'Platform name', 'rules' => ['required', 'string', 'max:255']],
            'support_email' => ['label' => 'Support email', 'rules' => ['nullable', 'email', 'max:255']],
        ],
        'security' => [
            'login_attempt_limit' => ['label' => 'Login attempt limit', 'rules' => ['required', 'integer', 'min:3', 'max:20']],
            'lockout_duration_minutes' => ['label' => 'Lockout duration (minutes)', 'rules' => ['required', 'integer', 'min:1', 'max:1440']],
            'password_expiry_days' => ['label' => 'Password expiry (days)', 'rules' => ['required', 'integer', 'min:0', 'max:365']],
        ],
    ];

    public function edit(): Response
    {
        $groups = collect(self::DEFINITIONS)->map(function (array $fields, string $group) {
            return collect($fields)->map(function (array $field, string $key) use ($group) {
                $setting = PlatformSetting::query()->where('group', $group)->where('key', $key)->first();

                return [
                    'key' => $key,
                    'label' => $field['label'],
                    'value' => data_get($setting?->value, 'value'),
                ];
            })->values();
        });

        return Inertia::render('Admin/Settings/Index', ['groups' => $groups]);
    }

    public function update(Request $request, string $group): RedirectResponse
    {
        abort_unless(array_key_exists($group, self::DEFINITIONS), 404);

        $fields = self::DEFINITIONS[$group];
        $rules = collect($fields)->mapWithKeys(fn (array $field, string $key) => [$key => $field['rules']])->all();
        $validated = $request->validate($rules);

        $oldValues = [];
        foreach ($validated as $key => $value) {
            $setting = PlatformSetting::query()->where('group', $group)->where('key', $key)->first();
            $oldValues[$key] = data_get($setting?->value, 'value');

            PlatformSetting::updateOrCreate(
                ['group' => $group, 'key' => $key],
                ['id' => $setting?->id ?? (string) Str::uuid(), 'value' => ['value' => $value]]
            );
        }

        app(AuditLogService::class)->record('platform_settings.updated', null, [
            'entity_type' => 'PlatformSetting',
            'entity_id' => $group,
            'old_values' => $oldValues,
            'new_values' => $validated,
        ]);

        return back()->with('status', 'Settings updated.');
    }
}
