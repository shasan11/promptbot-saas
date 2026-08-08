<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_invitations', function (Blueprint $table): void {
            $table->id();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('job_title')->nullable();
            $table->string('token_hash')->unique();
            $table->json('role_ids')->nullable();
            $table->json('team_ids')->nullable();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('locale')->nullable();
            $table->string('timezone')->nullable();
            $table->text('message')->nullable();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_sent_at')->nullable();
            $table->unsignedInteger('send_count')->default(1);
            $table->timestamps();

            $table->index(['email', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_invitations');
    }
};
