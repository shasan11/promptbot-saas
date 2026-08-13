<?php

namespace App\Http\Controllers\Portal;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\PortalLoginActivity;
use App\Models\PortalUserSession;
use App\Services\Platform\TotpService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;

class ProfileController extends PortalController
{
    public function edit(Request $request): Response
    {
        $user = $request->user('portal');
        return Inertia::render('Portal/Account/Profile', ['profile' => [...$user->toArray(), 'avatar_url' => $user->avatar_path ? Storage::disk('public')->url($user->avatar_path) : null]]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user('portal');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('portal_users')->ignore($user->getKey())],
            'phone' => ['nullable', 'string', 'max:30'], 'timezone' => ['nullable', 'timezone'],
            'locale' => ['nullable', 'string', 'max:10'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
        unset($data['avatar']);
        $emailChanged = strtolower($data['email']) !== strtolower($user->email);
        if ($emailChanged) $data['email_verified_at'] = null;
        if ($request->hasFile('avatar')) {
            $oldPath = $user->avatar_path;
            $data['avatar_path'] = $request->file('avatar')->store('portal-avatars', 'public');
            if ($oldPath && str_starts_with($oldPath, 'portal-avatars/')) Storage::disk('public')->delete($oldPath);
        }
        $user->update($data);
        if ($emailChanged) $user->sendEmailVerificationNotification();
        return back()->with('status', $emailChanged ? 'Profile updated. Verify your new email address before continuing.' : 'Profile updated.');
    }

    public function security(Request $request, TotpService $totp): Response
    {
        $user = $request->user('portal');
        $pending = $request->session()->get('portal.two_factor_pending');
        $secret = $pending ? Crypt::decryptString($pending) : null;
        $sessionHash = hash('sha256', $request->session()->getId());

        return Inertia::render('Portal/Account/Security', [
            'twoFactorEnabled' => $user->two_factor_enabled,
            'twoFactorSetup' => $secret ? ['secret' => $secret, 'uri' => $totp->uri($user, $secret)] : null,
            'recoveryCodes' => session('portal.two_factor_recovery_plain'),
            'sessions' => PortalUserSession::where('portal_user_id', $user->id)->whereNull('revoked_at')->latest('last_activity_at')->get()
                ->map(fn ($session) => [...$session->toArray(), 'current' => hash_equals($session->session_hash, $sessionHash)]),
            'loginActivity' => PortalLoginActivity::where('portal_user_id', $user->id)->latest('created_at')->limit(25)->get(),
        ]);
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password:portal'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
        $request->user('portal')->update(['password' => Hash::make($data['password'])]);
        return back()->with('status', 'Password updated.');
    }

    public function beginTwoFactor(Request $request, TotpService $totp): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password:portal']]);
        abort_if($request->user('portal')->two_factor_enabled, 422, 'Two-factor authentication is already enabled.');
        $request->session()->put('portal.two_factor_pending', Crypt::encryptString($totp->generateSecret()));
        return back()->with('status', 'Authenticator setup started.');
    }

    public function confirmTwoFactor(Request $request, TotpService $totp): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);
        $pending = $request->session()->get('portal.two_factor_pending');
        abort_unless($pending, 422, 'Start authenticator setup first.');
        $secret = Crypt::decryptString($pending);
        if (! $totp->verify($secret, $data['code'])) throw ValidationException::withMessages(['code' => 'The authentication code is invalid.']);
        $plainCodes = $totp->recoveryCodes();
        $request->user('portal')->forceFill([
            'two_factor_enabled' => true, 'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => collect($plainCodes)->map(fn ($code) => $totp->recoveryHash($code))->all(),
        ])->save();
        $request->session()->forget('portal.two_factor_pending');
        return back()->with('status', 'Two-factor authentication enabled.')->with('portal.two_factor_recovery_plain', $plainCodes);
    }

    public function disableTwoFactor(Request $request): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password:portal']]);
        $request->user('portal')->forceFill(['two_factor_enabled' => false, 'two_factor_secret' => null, 'two_factor_recovery_codes' => null])->save();
        $request->session()->forget('portal.two_factor_pending');
        return back()->with('status', 'Two-factor authentication disabled.');
    }

    public function revokeSession(Request $request, PortalUserSession $session): RedirectResponse
    {
        abort_unless($session->portal_user_id === $request->user('portal')->id, 404);
        $session->update(['revoked_at' => now()]);
        if (hash_equals($session->session_hash, hash('sha256', $request->session()->getId()))) {
            auth('portal')->logout();
            $request->session()->invalidate();
            return redirect()->route('portal.login');
        }
        return back()->with('status', 'Session revoked.');
    }
}
