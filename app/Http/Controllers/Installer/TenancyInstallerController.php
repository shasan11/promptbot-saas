<?php

namespace App\Http\Controllers\Installer;

use App\Http\Controllers\Controller;
use App\Services\Installer\LicenseValidationService;
use App\Services\Installer\TenancyInstallationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenancyInstallerController extends Controller
{
    public function index(TenancyInstallationService $installer): Response
    {
        return Inertia::render('Installer/Index', ['requirements' => $installer->requirements(), 'permissions' => $installer->permissions()]);
    }

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

    public function complete(Request $request, TenancyInstallationService $installer, LicenseValidationService $licenses): RedirectResponse
    {
        $data = $request->validate([
            'purchase_code' => ['nullable', 'string', 'max:255'],
            'app_name' => ['required', 'string', 'max:100'], 'app_url' => ['required', 'url', 'max:500'],
            'central_domains' => ['required', 'string', 'max:1000'], 'tenant_base_domain' => ['required', 'string', 'max:255'],
            'db_host' => ['required', 'string', 'max:255'], 'db_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'db_database' => ['required', 'string', 'max:64'], 'db_username' => ['required', 'string', 'max:128'], 'db_password' => ['nullable', 'string', 'max:1000'],
            'tenant_mode' => ['required', 'in:manual,cpanel,mysql'],
            'mail_mailer' => ['required', 'in:smtp,log,sendmail'], 'mail_host' => ['nullable', 'string', 'max:255'], 'mail_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:255'], 'mail_password' => ['nullable', 'string', 'max:1000'], 'mail_from_address' => ['required', 'email'],
            'admin_name' => ['required', 'string', 'max:255'], 'admin_email' => ['required', 'email', 'max:255'], 'admin_password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);
        abort_unless($licenses->validate($data['purchase_code'] ?? null), 422, 'Purchase code validation failed.');
        abort_unless(!in_array(false, $installer->requirements(), true) && !in_array(false, $installer->permissions(), true), 422, 'Server requirements or permissions are not satisfied.');
        abort_unless($installer->testCentralDatabase(['host'=>$data['db_host'],'port'=>$data['db_port'],'database'=>$data['db_database'],'username'=>$data['db_username'],'password'=>$data['db_password']??'']), 422, 'Database connection failed.');
        $installer->install($data);
        return redirect('/')->with('status', 'PromptBot installation completed.');
    }
}
