<?php

namespace App\Http\Controllers\Tenant\Admin\Administration;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Tenant\TenantAuditLogService;
use App\Services\Tenant\TenantSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceSettingsController extends Controller
{
    private const COMPANY_SIZES = ['1-10', '11-50', '51-200', '201-500', '500+'];

    private const LANGUAGES = ['en' => 'English', 'es' => 'Spanish', 'fr' => 'French', 'de' => 'German', 'pt' => 'Portuguese'];

    private const DATE_FORMATS = ['MM/DD/YYYY' => 'MM/DD/YYYY', 'DD/MM/YYYY' => 'DD/MM/YYYY', 'YYYY-MM-DD' => 'YYYY-MM-DD'];

    private const TIME_FORMATS = ['12h' => '12-hour', '24h' => '24-hour'];

    private const CURRENCIES = ['USD' => 'US Dollar', 'EUR' => 'Euro', 'GBP' => 'British Pound', 'INR' => 'Indian Rupee', 'AUD' => 'Australian Dollar'];

    public function __construct(
        private readonly TenantSettingsService $settings,
        private readonly TenantAuditLogService $auditLog,
    ) {}

    public function edit(Request $request, string $group = 'general'): Response
    {
        $groups = $this->groups();
        abort_unless(array_key_exists($group, $groups), 404);
        abort_unless($request->user('tenant')?->can('workspace.view'), 403);

        $fields = collect($groups[$group]['fields'])->map(function (array $field, string $key) use ($group) {
            $value = $this->settings->get("{$group}.{$key}");

            return [
                'key' => $key,
                'label' => $field['label'],
                'type' => $field['type'] ?? 'text',
                'help' => $field['help'] ?? null,
                'options' => collect($field['options'] ?? [])->map(fn ($label, $optionKey) => is_int($optionKey) ? [$label, $label] : [$optionKey, $label])->values(),
                'value' => ($field['type'] ?? 'text') === 'image' ? null : $value,
                'currentImageUrl' => ($field['type'] ?? 'text') === 'image' ? $value : null,
            ];
        })->values();

        return Inertia::render('Tenant/Admin/Administration/Workspace/Edit', [
            'group' => $group,
            'groups' => collect($groups)->map(fn ($g, $key) => ['key' => $key, 'title' => $g['title']])->values(),
            'title' => $groups[$group]['title'],
            'description' => $groups[$group]['description'],
            'fields' => $fields,
            'canUpdate' => $request->user('tenant')?->can($groups[$group]['permission']),
        ]);
    }

    public function update(Request $request, string $group): RedirectResponse
    {
        $groups = $this->groups();
        abort_unless(array_key_exists($group, $groups), 404);
        abort_unless($request->user('tenant')?->can($groups[$group]['permission']), 403);

        $fields = $groups[$group]['fields'];
        $imageKeys = collect($fields)->filter(fn ($f) => ($f['type'] ?? null) === 'image')->keys();

        $rules = collect($fields)
            ->reject(fn ($f, $key) => $imageKeys->contains($key))
            ->mapWithKeys(fn ($f, $key) => [$key => $f['rules']])
            ->all();

        foreach ($imageKeys as $key) {
            $rules[$key] = ['nullable', 'image', 'max:2048'];
            $rules["remove_{$key}"] = ['nullable', 'boolean'];
        }

        $validated = $request->validate($rules);
        $actor = $request->user('tenant');

        foreach ($fields as $key => $field) {
            $settingKey = "{$group}.{$key}";

            if (($field['type'] ?? null) === 'image') {
                $this->updateImage($request, $settingKey, $key, $actor);

                continue;
            }

            if (! array_key_exists($key, $validated)) {
                continue;
            }

            $this->settings->set($settingKey, $validated[$key], $actor, $this->auditLog);
        }

        return back()->with('status', $groups[$group]['title'].' settings updated.');
    }

    private function updateImage(Request $request, string $settingKey, string $fieldKey, ?User $actor): void
    {
        $current = $this->settings->get($settingKey);

        if ($request->hasFile($fieldKey)) {
            $path = $request->file($fieldKey)->store('workspace-branding', 'public');
            $url = Storage::disk('public')->url($path);

            $this->settings->set($settingKey, $url, $actor, $this->auditLog);

            if ($current) {
                $this->forgetStoredFile($current);
            }

            return;
        }

        if ($request->boolean("remove_{$fieldKey}") && $current) {
            $this->settings->forget($settingKey);
            $this->forgetStoredFile($current);
        }
    }

    private function forgetStoredFile(string $url): void
    {
        $path = ltrim(parse_url($url, PHP_URL_PATH) ?? '', '/');
        $relative = preg_replace('#^storage/#', '', $path);

        if ($relative && str_starts_with($relative, 'workspace-branding/')) {
            Storage::disk('public')->delete($relative);
        }
    }

    /** @return array<string, mixed> */
    private function groups(): array
    {
        return [
            'general' => [
                'title' => 'General',
                'description' => 'Core workspace identity used across the app and on outgoing communications.',
                'permission' => 'workspace.update',
                'fields' => [
                    'workspace_name' => ['label' => 'Workspace name', 'rules' => ['required', 'string', 'max:255']],
                    'legal_name' => ['label' => 'Legal business name', 'rules' => ['nullable', 'string', 'max:255']],
                    'support_email' => ['label' => 'Support email', 'rules' => ['nullable', 'email:rfc', 'max:255']],
                    'support_phone' => ['label' => 'Support phone', 'rules' => ['nullable', 'string', 'max:50']],
                    'website_url' => ['label' => 'Website URL', 'rules' => ['nullable', 'url', 'max:255']],
                    'industry' => ['label' => 'Industry', 'rules' => ['nullable', 'string', 'max:255']],
                    'company_size' => ['label' => 'Company size', 'type' => 'select', 'options' => self::COMPANY_SIZES, 'rules' => ['nullable', 'in:'.implode(',', self::COMPANY_SIZES)]],
                    'country' => ['label' => 'Country', 'rules' => ['nullable', 'string', 'max:255']],
                    'address' => ['label' => 'Address', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:500']],
                ],
            ],
            'branding' => [
                'title' => 'Branding',
                'description' => 'Logo, colors, and sender identity shown across the workspace and emails.',
                'permission' => 'workspace.manage_branding',
                'fields' => [
                    'logo' => ['label' => 'Logo', 'type' => 'image', 'rules' => []],
                    'favicon' => ['label' => 'Favicon', 'type' => 'image', 'rules' => []],
                    'primary_color' => ['label' => 'Primary color', 'type' => 'color', 'rules' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/']],
                    'secondary_color' => ['label' => 'Secondary color', 'type' => 'color', 'rules' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/']],
                    'sender_name' => ['label' => 'Mail sender name', 'help' => 'Appears as the "From" name on emails sent from this workspace.', 'rules' => ['nullable', 'string', 'max:255']],
                ],
            ],
            'localization' => [
                'title' => 'Localization',
                'description' => 'Language, timezone, and formatting defaults for this workspace.',
                'permission' => 'workspace.manage_localization',
                'fields' => [
                    'default_language' => ['label' => 'Default language', 'type' => 'select', 'options' => self::LANGUAGES, 'rules' => ['nullable', 'in:'.implode(',', array_keys(self::LANGUAGES))]],
                    'default_timezone' => ['label' => 'Default timezone', 'rules' => ['nullable', 'timezone']],
                    'date_format' => ['label' => 'Date format', 'type' => 'select', 'options' => self::DATE_FORMATS, 'rules' => ['nullable', 'in:'.implode(',', array_keys(self::DATE_FORMATS))]],
                    'time_format' => ['label' => 'Time format', 'type' => 'select', 'options' => self::TIME_FORMATS, 'rules' => ['nullable', 'in:12h,24h']],
                    'first_day_of_week' => ['label' => 'First day of week', 'type' => 'select', 'options' => ['sunday' => 'Sunday', 'monday' => 'Monday'], 'rules' => ['nullable', 'in:sunday,monday']],
                    'currency' => ['label' => 'Currency', 'type' => 'select', 'options' => self::CURRENCIES, 'rules' => ['nullable', 'in:'.implode(',', array_keys(self::CURRENCIES))]],
                ],
            ],
        ];
    }
}
