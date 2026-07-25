<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private array $tables = [];

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

    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'public_uuid')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('public_uuid'));
            }
        }
    }
};
