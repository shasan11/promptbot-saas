<?php

namespace App\Console\Commands\AI;

use App\Enums\TenantStatus;
use App\Models\AI\ApprovalRequest;
use App\Models\AI\Run;
use App\Models\Tenant;
use App\Services\AI\AISettingsService;
use Illuminate\Console\Command;

class MaintainAIDataCommand extends Command
{
    protected $signature = 'ai:maintain {--tenant=*}';
    protected $description = 'Expire stale AI approvals and purge old completed AI run data per tenant retention policy.';

    public function handle(): int
    {
        $ids = $this->option('tenant');
        Tenant::query()->where('status', TenantStatus::Active)->when($ids, fn ($query) => $query->whereIn('id', $ids))->each(function (Tenant $tenant): void {
            tenancy()->initialize($tenant);
            try {
                ApprovalRequest::query()->where('status', 'pending')->where('expires_at', '<=', now())->update(['status' => 'expired', 'updated_at' => now()]);
                $days = app(AISettingsService::class)->current()['log_retention_days'];
                Run::query()->whereIn('status', ['completed','failed','cancelled','timed_out','rate_limited'])->where('finished_at', '<', now()->subDays($days))->delete();
            } finally { tenancy()->end(); }
        });
        return self::SUCCESS;
    }
}
