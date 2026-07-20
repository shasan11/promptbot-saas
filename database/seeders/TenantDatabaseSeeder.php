<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $permissionIds = collect([
            'manage_users' => 'Manage users',
            'manage_settings' => 'Manage settings',
            'view_dashboard' => 'View dashboard',
        ])->map(fn (string $label, string $name) => Permission::firstOrCreate(['name' => $name], ['label' => $label])->id);

        collect([
            'tenant_owner' => 'Tenant Owner',
            'tenant_admin' => 'Tenant Administrator',
            'manager' => 'Manager',
            'staff' => 'Staff',
        ])->each(function (string $label, string $name) use ($permissionIds): void {
            $role = Role::firstOrCreate(['name' => $name], ['label' => $label]);
            $role->permissions()->syncWithoutDetaching($permissionIds->values()->all());
        });

        Setting::firstOrCreate(['key' => 'branding'], ['value' => ['logo' => null, 'sender_name' => tenant('company_name')]]);
    }
}
