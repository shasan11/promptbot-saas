<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_notification_preferences', function (Blueprint $table): void {
            $table->json('event_channels')->nullable()->after('product_email');
        });
    }

    public function down(): void
    {
        Schema::table('portal_notification_preferences', fn (Blueprint $table) => $table->dropColumn('event_channels'));
    }
};
