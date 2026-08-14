<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('portal_notification_preferences')) {
            Schema::create('portal_notification_preferences', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('portal_user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_account_id')->constrained()->cascadeOnDelete();
                $table->boolean('billing_email')->default(true);
                $table->boolean('workspace_email')->default(true);
                $table->boolean('support_email')->default(true);
                $table->boolean('security_email')->default(true);
                $table->boolean('product_email')->default(false);
                $table->timestamps();
                $table->unique(
                    ['portal_user_id', 'customer_account_id'],
                    'portal_notification_preference_unique'
                );
            });
        }

        if (Schema::hasTable('customer_account_users')
            && ! Schema::hasColumn('customer_account_users', 'service_access')) {
            Schema::table('customer_account_users', function (Blueprint $table): void {
                $table->string('service_access')->default('all')->after('can_manage_support')->index();
            });
        }

        if (Schema::hasTable('customer_account_invitations')) {
            $needsServiceAccess = ! Schema::hasColumn('customer_account_invitations', 'service_access');
            $needsTenantIds = ! Schema::hasColumn('customer_account_invitations', 'tenant_ids');

            if ($needsServiceAccess || $needsTenantIds) {
                Schema::table('customer_account_invitations', function (Blueprint $table) use ($needsServiceAccess, $needsTenantIds): void {
                    if ($needsServiceAccess) {
                        $table->string('service_access')->default('all')->after('can_manage_support');
                    }

                    if ($needsTenantIds) {
                        $table->json('tenant_ids')->nullable()->after('service_access');
                    }
                });
            }
        }
    }

    public function down(): void
    {
        // Intentionally left empty. These objects belong to the earlier customer
        // account migration; removing them here would damage a healthy schema.
    }
};
