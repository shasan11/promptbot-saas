<?php

namespace App\Http\Controllers\Portal\Auth;

use App\Enums\PortalUserStatus;
use App\Http\Controllers\Controller;
use App\Models\PortalLoginActivity;
use App\Models\PortalSocialAccount;
use App\Models\PortalUser;
use App\Services\Platform\CustomerAccountService;
use App\Services\Platform\PlatformSettingsService;
use App\Services\Platform\PortalAuthenticationService;
use App\Services\Platform\PublicPlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthenticationController extends Controller
{
    public function redirect(Request $request, PlatformSettingsService $settings): RedirectResponse
    {
        $this->ensureAvailable($settings);
        $interval = in_array($request->query('interval'), ['monthly', 'yearly'], true) ? $request->query('interval') : 'monthly';
        $request->session()->put('portal.oauth.context', [
            'intent' => $request->query('intent') === 'register' ? 'register' : 'login',
            'plan' => $request->query('plan'),
            'interval' => $interval,
        ]);

        return Socialite::driver('google')->scopes(['openid', 'profile', 'email'])->redirect();
    }

    public function callback(Request $request, PlatformSettingsService $settings, PortalAuthenticationService $authentication): RedirectResponse
    {
        $this->ensureAvailable($settings);
        try {
            $identity = Socialite::driver('google')->user();
        } catch (Throwable $exception) {
            report($exception);
            $this->failure($request, null, 'provider_callback_failed');

            return redirect()->route('portal.login')->withErrors(['email' => 'Google authentication could not be completed. Please try again.']);
        }

        $providerId = trim((string) $identity->getId());
        $email = strtolower(trim((string) $identity->getEmail()));
        $raw = $identity->getRaw();
        $emailVerified = filter_var($raw['verified_email'] ?? $raw['email_verified'] ?? false, FILTER_VALIDATE_BOOL);
        if ($providerId === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! $emailVerified) {
            $this->failure($request, $email ?: null, 'invalid_or_unverified_identity');

            return redirect()->route('portal.login')->withErrors(['email' => 'Google did not provide a usable verified email address.']);
        }

        $social = PortalSocialAccount::query()->where('provider', 'google')->where('provider_user_id', $providerId)->first();
        if ($social) {
            $social->update(['provider_email' => $email, 'avatar_url' => $identity->getAvatar()]);
            $this->selectAccount($request, $social->user);

            return $authentication->finish($request, $social->user, false, 'google');
        }

        $user = PortalUser::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($user) {
            if ($user->status !== PortalUserStatus::Active) {
                $this->failure($request, $email, 'inactive', $user->id);

                return redirect()->route('portal.login')->withErrors(['email' => 'This portal account is not active.']);
            }
            if ($user->socialAccounts()->where('provider', 'google')->exists()) {
                $this->failure($request, $email, 'google_identity_conflict', $user->id);

                return redirect()->route('portal.login')->withErrors(['email' => 'This account is already linked to a different Google identity.']);
            }
            $user->socialAccounts()->create(['provider' => 'google', 'provider_user_id' => $providerId, 'provider_email' => $email, 'avatar_url' => $identity->getAvatar()]);
            $this->selectAccount($request, $user);

            return $authentication->finish($request, $user, false, 'google');
        }

        if (! $this->registrationEnabled($settings)) {
            $this->failure($request, $email, 'registration_unavailable');

            return redirect()->route('portal.login')->withErrors(['email' => 'New customer registration is not currently available.']);
        }

        $context = $request->session()->get('portal.oauth.context', []);
        $request->session()->put('portal.oauth.pending_google', [
            'provider_user_id' => $providerId, 'email' => $email, 'name' => trim((string) $identity->getName()) ?: Str::before($email, '@'),
            'avatar_url' => $identity->getAvatar(), 'plan' => $context['plan'] ?? null, 'interval' => $context['interval'] ?? 'monthly',
        ]);

        return redirect()->route('portal.oauth.google.onboarding');
    }

    public function onboarding(Request $request, PublicPlanService $plans): Response|RedirectResponse
    {
        $pending = $request->session()->get('portal.oauth.pending_google');
        if (! is_array($pending)) {
            return redirect()->route('portal.login');
        }

        return Inertia::render('Portal/Auth/GoogleOnboarding', [
            'identity' => ['name' => $pending['name'], 'email' => $pending['email'], 'avatar' => $pending['avatar_url']],
            'selectedPlan' => $plans->query()->where('slug', $pending['plan'] ?? null)->first(),
            'interval' => $pending['interval'] ?? 'monthly',
        ]);
    }

    public function complete(Request $request, PlatformSettingsService $settings, PublicPlanService $plans, CustomerAccountService $accounts, PortalAuthenticationService $authentication): RedirectResponse
    {
        $this->ensureAvailable($settings);
        abort_unless($this->registrationEnabled($settings), 403, 'New customer registration is not currently available.');
        $pending = $request->session()->get('portal.oauth.pending_google');
        abort_unless(is_array($pending), 419, 'The Google onboarding session has expired.');
        $data = $request->validate([
            'account_name' => ['required', 'string', 'max:255'], 'timezone' => ['nullable', 'timezone'],
            'plan' => ['nullable', 'string', Rule::exists('plans', 'slug')->where(fn ($query) => $query->where('is_active', true)->where('is_public', true))],
            'interval' => ['required', 'in:monthly,yearly'],
        ]);
        if (filled($data['plan'] ?? null) && ! $plans->query()->where('slug', $data['plan'])->exists()) {
            throw ValidationException::withMessages(['plan' => 'The selected plan is not available for registration.']);
        }

        [$user, $account] = DB::transaction(function () use ($pending, $data, $accounts): array {
            $social = PortalSocialAccount::query()->where('provider', 'google')->where('provider_user_id', $pending['provider_user_id'])->lockForUpdate()->first();
            if ($social) {
                $existingAccount = $social->user->accounts()->oldest('customer_accounts.id')->firstOrFail();

                return [$social->user, $existingAccount];
            }
            if (PortalUser::query()->whereRaw('LOWER(email) = ?', [$pending['email']])->exists()) {
                throw ValidationException::withMessages(['account_name' => 'An account already exists for this email. Return to sign in and continue with Google.']);
            }
            $user = PortalUser::create([
                'name' => $pending['name'], 'email' => $pending['email'], 'password' => Str::random(64),
                'status' => PortalUserStatus::Active, 'timezone' => $data['timezone'] ?? null, 'email_verified_at' => now(),
            ]);
            $user->socialAccounts()->create(['provider' => 'google', 'provider_user_id' => $pending['provider_user_id'], 'provider_email' => $pending['email'], 'avatar_url' => $pending['avatar_url']]);
            $account = $accounts->createWithOwner($user, ['name' => $data['account_name'], 'timezone' => $data['timezone'] ?? 'UTC']);

            return [$user, $account];
        });

        $request->session()->forget(['portal.oauth.pending_google', 'portal.oauth.context']);
        $request->session()->put('portal.active_customer_account_id', $account->getKey());
        $request->session()->put('portal.purchase_selection', ['plan' => $data['plan'] ?? null, 'interval' => $data['interval']]);

        return $authentication->finish($request, $user, false, 'google', route('portal.workspaces.create', absolute: false));
    }

    private function ensureAvailable(PlatformSettingsService $settings): void
    {
        $enabled = filter_var($settings->get('customer_portal', 'google_login_enabled', false), FILTER_VALIDATE_BOOL);
        abort_unless($enabled && filled(config('services.google.client_id')) && filled(config('services.google.client_secret')) && filled(config('services.google.redirect')), 404);
    }

    private function registrationEnabled(PlatformSettingsService $settings): bool
    {
        $mode = $settings->get('registration', 'mode');
        if (in_array($mode, ['disabled', 'invitation_only'], true)) {
            return false;
        }
        if ($mode === 'enabled') {
            return true;
        }

        return filter_var($settings->get('registration', 'enabled', true), FILTER_VALIDATE_BOOL);
    }

    private function selectAccount(Request $request, PortalUser $user): void
    {
        if (! $request->session()->has('portal.active_customer_account_id')) {
            $accountId = $user->accounts()->oldest('customer_accounts.id')->value('customer_accounts.id');
            if ($accountId) {
                $request->session()->put('portal.active_customer_account_id', $accountId);
            }
        }
    }

    private function failure(Request $request, ?string $email, string $reason, ?int $userId = null): void
    {
        PortalLoginActivity::create(['portal_user_id' => $userId, 'email' => $email, 'event' => 'login.failed', 'successful' => false, 'ip_address' => $request->ip(), 'user_agent' => str($request->userAgent())->limit(1000), 'metadata' => ['provider' => 'google', 'reason' => $reason], 'created_at' => now()]);
    }
}
