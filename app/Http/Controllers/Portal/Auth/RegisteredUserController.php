<?php

namespace App\Http\Controllers\Portal\Auth;

use App\Enums\PortalUserStatus;
use App\Http\Controllers\Controller;
use App\Models\PortalUser;
use App\Services\Platform\CustomerAccountService;
use App\Services\Platform\PlatformSettingsService;
use App\Services\Platform\PublicPlanService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(Request $request, PlatformSettingsService $settings, PublicPlanService $plans): Response
    {
        abort_unless($this->registrationEnabled($settings), 404);

        return Inertia::render('Portal/Auth/Register', [
            'selectedPlan' => $plans->query()->where('slug', $request->query('plan'))->first(),
            'interval' => in_array($request->query('interval'), ['monthly', 'yearly'], true) ? $request->query('interval') : 'monthly',
        ]);
    }

    public function store(Request $request, CustomerAccountService $accounts, PlatformSettingsService $settings, PublicPlanService $plans): RedirectResponse
    {
        abort_unless($this->registrationEnabled($settings), 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:portal_users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'account_name' => ['required', 'string', 'max:255'],
            'timezone' => ['nullable', 'timezone'],
            'plan' => ['nullable', 'string', Rule::exists('plans', 'slug')->where(fn ($query) => $query->where('is_active', true)->where('is_public', true))],
            'interval' => ['nullable', 'in:monthly,yearly'],
        ]);
        if (filled($data['plan'] ?? null) && ! $plans->query()->where('slug', $data['plan'])->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['plan' => 'The selected plan is not available for registration.']);
        }

        $verificationRequired = filter_var($settings->get('registration', 'email_verification_required', true), FILTER_VALIDATE_BOOL);
        [$user, $account] = DB::transaction(function () use ($data, $accounts, $verificationRequired): array {
            $user = PortalUser::create([
                'name' => $data['name'], 'email' => strtolower($data['email']), 'password' => $data['password'],
                'status' => PortalUserStatus::Active, 'timezone' => $data['timezone'] ?? null,
                'email_verified_at' => $verificationRequired ? null : now(),
            ]);
            $account = $accounts->createWithOwner($user, ['name' => $data['account_name'], 'timezone' => $data['timezone'] ?? 'UTC']);
            return [$user, $account];
        });

        event(new Registered($user));
        Auth::guard('portal')->login($user);
        $request->session()->put('portal.active_customer_account_id', $account->getKey());
        $request->session()->put('portal.purchase_selection', ['plan' => $data['plan'] ?? null, 'interval' => $data['interval'] ?? 'monthly']);

        return redirect()->route('portal.workspaces.create');
    }

    private function registrationEnabled(PlatformSettingsService $settings): bool
    {
        $legacyMode = $settings->get('registration', 'mode');
        if (in_array($legacyMode, ['disabled', 'invitation_only'], true)) {
            return false;
        }
        if ($legacyMode === 'enabled') {
            return true;
        }
        $value = $settings->get('registration', 'enabled', $legacyMode === null || $legacyMode === 'enabled');

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }
}
