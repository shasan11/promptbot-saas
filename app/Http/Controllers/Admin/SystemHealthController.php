<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Platform\AuditLogService;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SystemHealthController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/SystemHealth/Index', [
            'checks' => [
                $this->databaseCheck(),
                $this->cacheCheck(),
                $this->storageCheck(),
                [
                    'name' => 'Queue',
                    'status' => config('queue.default') === 'sync' ? 'warning' : 'healthy',
                    'detail' => 'Driver: '.config('queue.default'),
                ],
                [
                    'name' => 'Application',
                    'status' => config('app.debug') && app()->environment('production') ? 'warning' : 'healthy',
                    'detail' => sprintf('%s · Laravel %s · PHP %s', app()->environment(), Application::VERSION, PHP_VERSION),
                ],
                [
                    'name' => 'Disk space',
                    'status' => $this->diskFreeBytes() < 512 * 1024 * 1024 ? 'warning' : 'healthy',
                    'detail' => $this->formatBytes($this->diskFreeBytes()).' free',
                ],
            ],
            'queue' => [
                'driver' => config('queue.default'),
                'pending' => $this->tableCount('jobs'),
                'failed' => $this->tableCount('failed_jobs'),
            ],
            'maintenance' => [
                'enabled' => app()->isDownForMaintenance(),
                'lastMigration' => Schema::hasTable('migrations') ? DB::table('migrations')->orderByDesc('batch')->orderByDesc('migration')->value('migration') : null,
            ],
            'failedJobs' => Schema::hasTable('failed_jobs')
                ? DB::table('failed_jobs')->select(['id', 'uuid', 'connection', 'queue', 'exception', 'failed_at'])->orderByDesc('failed_at')->limit(20)->get()
                : [],
        ]);
    }

    public function clearCaches(Request $request, AuditLogService $auditLog): RedirectResponse
    {
        abort_unless($request->user('central')?->can('maintenance.manage'), 403);

        Artisan::call('optimize:clear');
        $auditLog->record('system.caches_cleared', null, [
            'entity_type' => 'SystemHealth',
            'entity_id' => 'cache',
            'severity' => 'warning',
        ]);

        return back()->with('status', 'Application, route, config, event, and view caches cleared.');
    }

    public function retryFailed(Request $request, string $failedJob, AuditLogService $auditLog): RedirectResponse
    {
        abort_unless($request->user('central')?->can('maintenance.manage'), 403);

        Artisan::call('queue:retry', ['id' => [$failedJob]]);
        $auditLog->record('queue.failed_job_retried', null, [
            'entity_type' => 'FailedJob',
            'entity_id' => $failedJob,
        ]);

        return back()->with('status', 'Failed job queued for retry.');
    }

    public function retryAll(Request $request, AuditLogService $auditLog): RedirectResponse
    {
        abort_unless($request->user('central')?->can('maintenance.manage'), 403);

        Artisan::call('queue:retry', ['id' => ['all']]);
        $auditLog->record('queue.all_failed_jobs_retried', null, [
            'entity_type' => 'FailedJob',
            'entity_id' => 'all',
            'severity' => 'warning',
        ]);

        return back()->with('status', 'All failed jobs queued for retry.');
    }

    public function forgetFailed(Request $request, string $failedJob, AuditLogService $auditLog): RedirectResponse
    {
        abort_unless($request->user('central')?->can('maintenance.manage'), 403);

        Artisan::call('queue:forget', ['id' => $failedJob]);
        $auditLog->record('queue.failed_job_forgotten', null, [
            'entity_type' => 'FailedJob',
            'entity_id' => $failedJob,
            'severity' => 'warning',
        ]);

        return back()->with('status', 'Failed job removed.');
    }

    public function flushFailed(Request $request, AuditLogService $auditLog): RedirectResponse
    {
        abort_unless($request->user('central')?->can('maintenance.manage'), 403);

        Artisan::call('queue:flush');
        $auditLog->record('queue.failed_jobs_flushed', null, [
            'entity_type' => 'FailedJob',
            'entity_id' => 'all',
            'severity' => 'critical',
        ]);

        return back()->with('status', 'All failed job records removed.');
    }

    private function databaseCheck(): array
    {
        try {
            DB::connection()->getPdo();

            return ['name' => 'Database', 'status' => 'healthy', 'detail' => 'Central database connected'];
        } catch (Throwable $exception) {
            return ['name' => 'Database', 'status' => 'error', 'detail' => $exception->getMessage()];
        }
    }

    private function cacheCheck(): array
    {
        $key = 'system-health:'.uniqid();

        try {
            Cache::put($key, 'ok', 10);
            $healthy = Cache::get($key) === 'ok';
            Cache::forget($key);

            return [
                'name' => 'Cache',
                'status' => $healthy ? 'healthy' : 'error',
                'detail' => 'Store: '.config('cache.default'),
            ];
        } catch (Throwable $exception) {
            return ['name' => 'Cache', 'status' => 'error', 'detail' => $exception->getMessage()];
        }
    }

    private function storageCheck(): array
    {
        $path = 'health-check/'.uniqid().'.txt';

        try {
            Storage::disk('local')->put($path, 'ok');
            $healthy = Storage::disk('local')->get($path) === 'ok';
            Storage::disk('local')->delete($path);

            return [
                'name' => 'Storage',
                'status' => $healthy ? 'healthy' : 'error',
                'detail' => 'Local storage is readable and writable',
            ];
        } catch (Throwable $exception) {
            return ['name' => 'Storage', 'status' => 'error', 'detail' => $exception->getMessage()];
        }
    }

    private function tableCount(string $table): int|string
    {
        return Schema::hasTable($table) ? DB::table($table)->count() : 'Not installed';
    }

    private function diskFreeBytes(): int
    {
        $bytes = @disk_free_space(storage_path());

        return is_numeric($bytes) ? (int) $bytes : 0;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return 'Unknown';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return round($bytes / (1024 ** $power), 1).' '.$units[$power];
    }
}
