<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private array $tables = [
        'central_users',
        'tenants',
        'domains',
        'plans',
        'features',
        'subscriptions',
        'provisioning_logs',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'public_uuid')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->uuid('public_uuid')->nullable()->unique()->after('id');
            });

            DB::table($table)
                ->whereNull('public_uuid')
                ->orderBy('id')
                ->lazyById()
                ->each(fn ($record) => DB::table($table)->where('id', $record->id)->update(['public_uuid' => (string) Str::uuid()]));
        }

        if (Schema::hasTable('central_users')) {
            Schema::table('central_users', function (Blueprint $table): void {
                if (! Schema::hasColumn('central_users', 'phone')) {
                    $table->string('phone')->nullable()->after('email');
                }
                if (! Schema::hasColumn('central_users', 'avatar_path')) {
                    $table->string('avatar_path')->nullable()->after('phone');
                }
                if (! Schema::hasColumn('central_users', 'department')) {
                    $table->string('department')->nullable()->after('role');
                }
                if (! Schema::hasColumn('central_users', 'last_login_at')) {
                    $table->timestamp('last_login_at')->nullable()->after('is_active');
                }
                if (! Schema::hasColumn('central_users', 'last_login_ip')) {
                    $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
                }
                if (! Schema::hasColumn('central_users', 'locked_until')) {
                    $table->timestamp('locked_until')->nullable()->after('last_login_ip');
                }
                if (! Schema::hasColumn('central_users', 'password_expires_at')) {
                    $table->timestamp('password_expires_at')->nullable()->after('locked_until');
                }
                if (! Schema::hasColumn('central_users', 'two_factor_required')) {
                    $table->boolean('two_factor_required')->default(false)->after('password_expires_at');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'public_uuid')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('public_uuid'));
            }
        }

        if (Schema::hasTable('central_users')) {
            Schema::table('central_users', function (Blueprint $table): void {
                foreach (['phone', 'avatar_path', 'department', 'last_login_at', 'last_login_ip', 'locked_until', 'password_expires_at', 'two_factor_required'] as $column) {
                    if (Schema::hasColumn('central_users', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
