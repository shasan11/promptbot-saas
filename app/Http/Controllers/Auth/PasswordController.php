<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\CentralUser;
use App\Services\Platform\AuditLogService;
use App\Services\Platform\SecuritySettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request, SecuritySettings $security, AuditLogService $audit): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $user = $request->user();

        $attributes = ['password' => Hash::make($validated['password'])];

        if ($user instanceof CentralUser) {
            $attributes['password_expires_at'] = now()->addDays($security->passwordExpiryDays());
        }

        $user->update($attributes);

        if ($user instanceof CentralUser) {
            $audit->record('platform_admin.password_changed', $user);
        }

        return back();
    }
}
