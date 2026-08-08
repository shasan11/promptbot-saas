<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_hour_policies', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('timezone');
            $table->boolean('is_default')->default(false);
            $table->string('status')->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('business_hour_intervals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('policy_id')->constrained('business_hour_policies')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();

            $table->index(['policy_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_hour_intervals');
        Schema::dropIfExists('business_hour_policies');
    }
};
