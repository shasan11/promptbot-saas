<?php

namespace App\Console\Commands\Knowledge;

use App\Enums\TenantStatus;
use App\Jobs\Knowledge\SyncKnowledgeSourceJob;
use App\Models\Tenant;
use App\Services\Knowledge\KnowledgeSyncService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Dispatches sync jobs for every tenant source whose schedule is due.
 *
 * Runs from the central scheduler and walks tenants explicitly: there is no
 * cross-tenant query that could find "all due sources", because each workspace's
 * data lives in its own database.
 */
class SyncKnowledgeSourcesCommand extends Command
{
    protected $signature = 'knowledge:sync-sources
                            {--tenant=* : Limit to specific tenant IDs}
                            {--limit=50 : Maximum sources to dispatch per tenant}';

    protected $description = 'Dispatch synchronisation jobs for knowledge sources that are due';

    public function handle(KnowledgeSyncService $sync): int
    {
        $tenants = Tenant::query()
            ->where('status', TenantStatus::Active)
            ->when($this->option('tenant'), fn ($query, $ids) => $query->whereIn('id', $ids))
            ->get();

        $dispatched = 0;

        foreach ($tenants as $tenant) {
            try {
                tenancy()->initialize($tenant);

                $due = $sync->dueSources((int) $this->option('limit'));

                foreach ($due as $source) {
                    // Marked queued before dispatch so the scheduler's next run
                    // (a minute later) does not pick the same source up again
                    // while the first job is still waiting for a worker.
                    $source->forceFill(['sync_status' => \App\Enums\Knowledge\SyncStatus::Queued->value])->save();

                    SyncKnowledgeSourceJob::dispatch($source->id);
                    $dispatched++;
                }

                if ($due->isNotEmpty()) {
                    $this->line("  {$tenant->id}: dispatched {$due->count()} source(s)");
                }
            } catch (Throwable $e) {
                // One tenant's broken database must not stop the sweep for
                // everyone else.
                $this->error("  {$tenant->id}: {$e->getMessage()}");
            } finally {
                if (tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        }

        $this->info("Dispatched {$dispatched} knowledge sync job(s) across {$tenants->count()} tenant(s).");

        return self::SUCCESS;
    }
}
