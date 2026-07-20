<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Tenant/Admin/Settings/Edit', [
            'tenant' => [
                'id' => tenant('id'),
                'company_name' => tenant('company_name'),
            ],
            'settings' => [
                'companyName' => Setting::query()->where('key', 'company.name')->value('value')['value'] ?? tenant('company_name'),
                'branding' => Setting::query()->where('key', 'branding')->value('value') ?? [],
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'sender_name' => ['nullable', 'string', 'max:255'],
        ]);

        Setting::query()->updateOrCreate(
            ['key' => 'company.name'],
            ['value' => ['value' => $validated['company_name']]]
        );

        Setting::query()->updateOrCreate(
            ['key' => 'branding'],
            ['value' => [
                'logo' => null,
                'sender_name' => $validated['sender_name'] ?: $validated['company_name'],
            ]]
        );

        return back()->with('status', 'Tenant settings updated.');
    }
}
