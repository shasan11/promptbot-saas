<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user('tenant')->load(['roles:id,name,label', 'teams:id,name', 'department:id,name']);

        return Inertia::render('Tenant/Admin/Profile/Edit', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'jobTitle' => $user->job_title,
                'locale' => $user->locale ?: 'en',
                'timezone' => $user->timezone ?: config('app.timezone'),
                'avatarUrl' => $user->avatar_path ? '/storage/'.ltrim($user->avatar_path, '/') : null,
                'status' => $user->status->value,
                'lastLoginAt' => $user->last_login_at,
                'passwordChangedAt' => $user->password_changed_at,
                'createdAt' => $user->created_at,
                'department' => $user->department,
                'roles' => $user->roles,
                'teams' => $user->teams,
            ],
            'locales' => [
                ['value' => 'en', 'label' => 'English'],
                ['value' => 'es', 'label' => 'Spanish'],
                ['value' => 'fr', 'label' => 'French'],
                ['value' => 'de', 'label' => 'German'],
                ['value' => 'pt', 'label' => 'Portuguese'],
            ],
            'timezones' => collect(timezone_identifiers_list())->map(fn (string $timezone) => ['value' => $timezone, 'label' => str_replace('_', ' ', $timezone)]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user('tenant');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'locale' => ['required', Rule::in(['en', 'es', 'fr', 'de', 'pt'])],
            'timezone' => ['required', 'timezone'],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('remove_avatar') && $user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $data['avatar_path'] = null;
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $data['avatar_path'] = $request->file('avatar')->store('tenant-avatars/'.tenant('id'), 'public');
        }

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'locale' => $data['locale'],
            'timezone' => $data['timezone'],
            ...(array_key_exists('avatar_path', $data) ? ['avatar_path' => $data['avatar_path']] : []),
        ]);

        return back()->with('status', 'Profile updated.');
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password:tenant'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user('tenant')->update([
            'password' => Hash::make($data['password']),
            'password_changed_at' => now(),
        ]);

        return back()->with('status', 'Password updated.');
    }
}
