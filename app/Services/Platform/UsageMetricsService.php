<?php

namespace App\Services\Platform;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UsageMetricsService
{
    public function summary(): array
    {
        return [
            'messages_processed' => $this->metric('messages'),
            'ai_tokens_consumed' => $this->metric('ai_tokens'),
            'voice_minutes_consumed' => $this->metric('voice_minutes'),
            'storage_used' => $this->metric('storage_mb'),
            'by_metric' => Schema::hasTable('usage_metrics')
                ? DB::table('usage_metrics')->selectRaw('metric, sum(quantity) as total')->groupBy('metric')->orderBy('metric')->get()
                : collect(),
        ];
    }

    private function metric(string $metric): float
    {
        return Schema::hasTable('usage_metrics') ? (float) DB::table('usage_metrics')->where('metric', $metric)->sum('quantity') : 0.0;
    }
}
