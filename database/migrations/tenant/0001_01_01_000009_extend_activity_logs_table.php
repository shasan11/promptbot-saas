<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->string('actor_name')->nullable()->after('user_id');
            $table->string('subject_label')->nullable()->after('subject_id');
            $table->text('description')->nullable()->after('subject_label');
            $table->json('old_values')->nullable()->after('description');
            $table->json('new_values')->nullable()->after('old_values');
            $table->string('ip_address')->nullable()->after('properties');
            $table->string('user_agent')->nullable()->after('ip_address');
            $table->uuid('request_id')->nullable()->after('user_agent');

            $table->index('event');
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->dropColumn([
                'actor_name', 'subject_label', 'description', 'old_values', 'new_values',
                'ip_address', 'user_agent', 'request_id',
            ]);
        });
    }
};
