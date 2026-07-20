<?php

namespace App\Services\Tenancy;

class TenantMailBrandingService
{
    public function branding(): array
    {
        if (! tenancy()->initialized) {
            return [
                'name' => config('app.name'),
                'url' => config('app.url'),
                'from' => config('mail.from'),
            ];
        }

        return [
            'name' => tenant('company_name'),
            'url' => request()?->getSchemeAndHttpHost(),
            'from' => [
                'address' => config('mail.from.address'),
                'name' => tenant('company_name') ?: config('mail.from.name'),
            ],
        ];
    }
}
