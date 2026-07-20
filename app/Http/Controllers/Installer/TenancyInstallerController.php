<?php

namespace App\Http\Controllers\Installer;

use App\Http\Controllers\Controller;
use App\Services\Installer\LicenseValidationService;
use App\Services\Installer\TenancyInstallationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenancyInstallerController extends Controller
{
    public function status(TenancyInstallationService $installer): JsonResponse
    {
        return response()->json([
            'installed' => $installer->installed(),
            'requirements' => $installer->requirements(),
            'permissions' => $installer->permissions(),
            'tenant_provisioning_mode' => config('saas.db_provisioning_mode'),
        ]);
    }

    public function license(Request $request, LicenseValidationService $licenses): JsonResponse
    {
        $request->validate(['purchase_code' => ['nullable', 'string', 'max:255']]);

        return response()->json(['valid' => $licenses->validate($request->string('purchase_code')->toString())]);
    }

    public function tenantProvisioning(Request $request, TenancyInstallationService $installer): JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['required', 'in:manual,cpanel,mysql'],
            'host' => ['nullable', 'string'],
            'port' => ['nullable', 'integer'],
            'database' => ['nullable', 'string'],
            'username' => ['nullable', 'string'],
            'password' => ['nullable', 'string'],
        ]);

        return response()->json(['valid' => $installer->testTenantProvisioning($validated)]);
    }
}
