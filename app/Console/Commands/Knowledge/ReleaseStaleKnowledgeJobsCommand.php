<?php

namespace App\Console\Commands\Knowledge;

use App\Enums\Knowledge\ProcessingJobStatus;
use App\Enums\TenantStatus;
use App\Models\Knowledge\KnowledgeProcessingJob;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Throwable;

/**
 * Closes out processing jobs whose worker died without reporting.
 *
 * A killed worker (OOM, deploy, host restart) leaves its row stuck at "running"
 * forever. Left alone, the Processing Queue page fills with phantom work and
 * operators cannot tell real activity from residue. This marks anything running
 * past the stale threshold as failed, with an honest explanation.
 */
class ReleaseStaleKnowledgeJobsCommand extends Command
{
    protected $signature = 'knowledge:release-stale-jobs {--tenant=* : Limit to specific tenant IDs}';

    protected $description = 'Fail knowledge processing jobs whose worker died without reporting';

    public function handle(): int
    {
        $tenants = Tenant::query()
            ->where('status', TenantStatus::Active)
            ->when($this->option('tenant'), fn ($query, $ids) => $query->whereIn('id', $ids))
            ->get();

        $released = 0;

        foreach ($tenants as $tenant) {
            try {
                tenancy()->initialize($tenant);

                $released += KnowledgeProcessingJob::query()->stale()->update([
                    'status' => ProcessingJobStatus::Failed->value,
                    'finished_at' => now(),
                    'last_error' => 'The worker processing this job stopped responding. Retry the source.',
                ]);
            } catch (Throwable $e) {
                $this->error("  {$tenant->id}: {$e->getMessage()}");
            } finally {
                if (tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        }

        $this->info("Released {$released} stale knowledge job(s).");

        return self::SUCCESS;
    }
}
