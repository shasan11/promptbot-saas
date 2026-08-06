<?php

namespace App\Http\Requests\Auth;

use App\Models\CentralUser;
use App\Models\PlatformAdminLoginAttempt;
use App\Services\Platform\SecuritySettings;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $security = app(SecuritySettings::class);

        $this->ensureIsNotRateLimited($security);

        $user = CentralUser::query()->where('email', $this->string('email'))->first();

        if ($user?->locked_until?->isFuture()) {
            $this->recordLoginAttempt($user, false, 'locked');

            throw ValidationException::withMessages([
                'email' => __('This administrator account is temporarily locked.'),
            ]);
        }

        if ($user && ! $user->is_active) {
            $this->recordLoginAttempt($user, false, 'inactive');

            throw ValidationException::withMessages([
                'email' => __('This administrator account has been deactivated.'),
            ]);
        }

        if (! Auth::guard('central')->attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());
            $this->recordLoginAttempt($user, false, 'invalid_credentials');

            if (RateLimiter::tooManyAttempts($this->throttleKey(), $security->loginAttemptLimit()) && $user) {
                $user->forceFill(['locked_until' => now()->addMinutes($security->lockoutDurationMinutes())])->save();
            }

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        /** @var CentralUser|null $authenticated */
        $authenticated = Auth::guard('central')->user();
        $authenticated?->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $this->ip(),
            'locked_until' => null,
        ])->save();

        $this->recordLoginAttempt($authenticated, true);
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(?SecuritySettings $security = null): void
    {
        $security ??= app(SecuritySettings::class);

        if (! RateLimiter::tooManyAttempts($this->throttleKey(), $security->loginAttemptLimit())) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate('central|'.Str::lower($this->string('email')).'|'.$this->ip());
    }

    private function recordLoginAttempt(?CentralUser $user, bool $successful, ?string $failureReason = null): void
    {
        PlatformAdminLoginAttempt::create([
            'administrator_id' => $user?->id,
            'email' => $this->string('email')->toString(),
            'successful' => $successful,
            'ip_address' => $this->ip(),
            'user_agent' => $this->userAgent(),
            'failure_reason' => $failureReason,
            'attempted_at' => now(),
        ]);
    }
}
