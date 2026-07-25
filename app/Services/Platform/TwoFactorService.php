<?php

namespace App\Services\Platform;

use App\Models\CentralUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TwoFactorService
{
    public function generateSecret(CentralUser $user): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = collect(range(1, 32))
            ->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])
            ->implode('');

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => $this->hashRecoveryCodes($this->plainRecoveryCodes()),
            'two_factor_recovery_codes_regenerated_at' => now(),
        ])->save();

        return $secret;
    }

    public function confirm(CentralUser $user, string $code): bool
    {
        if (! $user->two_factor_secret || ! $this->verifyTotp($user->two_factor_secret, $code)) {
            return false;
        }

        $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_required' => true])->save();

        return true;
    }

    public function disable(CentralUser $user): void
    {
        abort_if($user->hasRole('Platform Owner'), 422, 'Platform Owner accounts must keep two-factor authentication enabled.');

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_required' => false,
        ])->save();
    }

    public function regenerateRecoveryCodes(CentralUser $user): array
    {
        $codes = $this->plainRecoveryCodes();
        $user->forceFill([
            'two_factor_recovery_codes' => $this->hashRecoveryCodes($codes),
            'two_factor_recovery_codes_regenerated_at' => now(),
        ])->save();

        return $codes;
    }

    public function verifyChallenge(CentralUser $user, string $code): bool
    {
        return $user->two_factor_secret && $this->verifyTotp($user->two_factor_secret, $code);
    }

    public function consumeRecoveryCode(CentralUser $user, string $code): bool
    {
        $codes = collect($user->two_factor_recovery_codes ?? []);
        $match = $codes->first(fn (string $hash) => Hash::check($code, $hash));

        if (! $match) {
            return false;
        }

        $user->forceFill([
            'two_factor_recovery_codes' => $codes->reject(fn (string $hash) => $hash === $match)->values()->all(),
        ])->save();

        return true;
    }

    public function provisioningUri(CentralUser $user): ?string
    {
        if (! $user->two_factor_secret) {
            return null;
        }

        $label = rawurlencode(config('app.name', 'PromptBot').':'.$user->email);
        $issuer = rawurlencode(config('app.name', 'PromptBot'));

        return "otpauth://totp/{$label}?secret={$user->two_factor_secret}&issuer={$issuer}&digits=6&period=30";
    }

    private function plainRecoveryCodes(): array
    {
        return Collection::times(8, fn () => Str::lower(Str::random(5).'-'.Str::random(5)))
            ->all();
    }

    private function hashRecoveryCodes(array $codes): array
    {
        return collect($codes)->map(fn (string $code) => Hash::make($code))->all();
    }

    private function verifyTotp(string $secret, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $timeSlice = intdiv(time(), 30);

        foreach ([-1, 0, 1] as $offset) {
            if (hash_equals($this->totp($secret, $timeSlice + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    private function totp(string $secret, int $timeSlice): string
    {
        $key = $this->base32Decode($secret);
        $time = pack('N*', 0).pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $time, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $value = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');
        $buffer = 0;
        $bitsLeft = 0;
        $result = '';

        foreach (str_split($secret) as $char) {
            $position = strpos($alphabet, $char);

            if ($position === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $position;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $result .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $result;
    }
}
