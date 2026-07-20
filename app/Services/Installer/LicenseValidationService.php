<?php

namespace App\Services\Installer;

use Illuminate\Support\Facades\Http;

class LicenseValidationService
{
    public function validate(?string $purchaseCode): bool
    {
        if (! config('installer.license.enabled')) {
            return true;
        }

        $endpoint = config('installer.license.endpoint');

        if (! $endpoint || ! $purchaseCode) {
            return false;
        }

        return Http::asJson()
            ->post($endpoint, ['purchase_code' => $purchaseCode, 'domain' => request()?->getHost()])
            ->successful();
    }
}
