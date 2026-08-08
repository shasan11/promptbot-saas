<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone')->nullable()->after('email');
            $table->string('job_title')->nullable()->after('phone');
            $table->string('avatar_path')->nullable()->after('job_title');
            $table->string('status')->default('active')->after('avatar_path');
            $table->string('locale')->nullable()->after('status');
            $table->string('timezone')->nullable()->after('locale');
            $table->foreignId('department_id')->nullable()->after('timezone')->constrained('departments')->nullOnDelete();
            $table->timestamp('last_login_at')->nullable()->after('department_id');
            $table->string('last_login_ip')->nullable()->after('last_login_at');
            $table->timestamp('password_changed_at')->nullable()->after('last_login_ip');
            $table->timestamp('account_expires_at')->nullable()->after('password_changed_at');
            $table->timestamp('suspended_at')->nullable()->after('account_expires_at');
            $table->foreignId('suspended_by')->nullable()->after('suspended_at')->constrained('users')->nullOnDelete();
            $table->string('suspension_reason')->nullable()->after('suspended_by');
            $table->timestamp('deactivated_at')->nullable()->after('suspension_reason');
            $table->foreignId('created_by')->nullable()->after('deactivated_at')->constrained('users')->nullOnDelete();

            $table->index('status');
            $table->index('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('suspended_by');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn([
                'phone', 'job_title', 'avatar_path', 'status', 'locale', 'timezone',
                'last_login_at', 'last_login_ip', 'password_changed_at', 'account_expires_at',
                'suspended_at', 'suspension_reason', 'deactivated_at',
            ]);
        });
    }
};
