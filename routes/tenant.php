<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Admin\Administration\BusinessHourController;
use App\Http\Controllers\Tenant\Admin\Administration\DepartmentController;
use App\Http\Controllers\Tenant\Admin\Administration\HolidayController;
use App\Http\Controllers\Tenant\Admin\Administration\InvitationController;
use App\Http\Controllers\Tenant\Admin\Administration\OverviewController;
use App\Http\Controllers\Tenant\Admin\Administration\RoleController;
use App\Http\Controllers\Tenant\Admin\Administration\TeamController;
use App\Http\Controllers\Tenant\Admin\Administration\UserController as AdministrationUserController;
use App\Http\Controllers\Tenant\Admin\Administration\WorkspaceSettingsController;
use App\Http\Controllers\Tenant\Admin\Connections\ConnectionController;
use App\Http\Controllers\Tenant\Admin\Connections\CredentialController;
use App\Http\Controllers\Tenant\Admin\Connections\DataSourceController;
use App\Http\Controllers\Tenant\Admin\Connections\IntegrationCatalogController;
use App\Http\Controllers\Tenant\Admin\Connections\OperationsController;
use App\Http\Controllers\Tenant\Admin\Connections\OverviewController as ConnectionsOverviewController;
use App\Http\Controllers\Tenant\Admin\Connections\WebhookEventController;
use App\Http\Controllers\Tenant\Admin\DashboardController as TenantAdminDashboardController;
use App\Http\Controllers\Tenant\Admin\Knowledge\CollectionController as KnowledgeCollectionController;
use App\Http\Controllers\Tenant\Admin\Knowledge\DocumentController as KnowledgeDocumentController;
use App\Http\Controllers\Tenant\Admin\Knowledge\FaqController as KnowledgeFaqController;
use App\Http\Controllers\Tenant\Admin\Knowledge\KnowledgeBaseController;
use App\Http\Controllers\Tenant\Admin\Knowledge\KnowledgeSettingsController;
use App\Http\Controllers\Tenant\Admin\Knowledge\ManualTextController as KnowledgeManualTextController;
use App\Http\Controllers\Tenant\Admin\Knowledge\OperationsController as KnowledgeOperationsController;
use App\Http\Controllers\Tenant\Admin\Knowledge\OverviewController as KnowledgeOverviewController;
use App\Http\Controllers\Tenant\Admin\Knowledge\PlaygroundController as KnowledgePlaygroundController;
use App\Http\Controllers\Tenant\Admin\Knowledge\SourceController as KnowledgeSourceController;
use App\Http\Controllers\Tenant\Admin\Knowledge\WebsiteController as KnowledgeWebsiteController;
use App\Http\Controllers\Tenant\Auth\TenantAuthenticatedSessionController;
use App\Http\Controllers\Tenant\Connections\InboundWebhookController;
use App\Http\Controllers\Tenant\InvitationAcceptController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Feel free to customize them however you want. Good luck!
|
*/

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    'tenant.active',
])->group(function () {
    Route::middleware('guest:tenant')->group(function (): void {
        Route::redirect('/tenant/login', '/login');
        Route::get('/login', [TenantAuthenticatedSessionController::class, 'create'])->name('tenant.login');
        Route::post('/login', [TenantAuthenticatedSessionController::class, 'store']);

        Route::get('/invitation/{token}', [InvitationAcceptController::class, 'show'])->name('tenant.invitation.show');
        Route::post('/invitation/{token}/accept', [InvitationAcceptController::class, 'accept'])->name('tenant.invitation.accept');
    });

    Route::post('/logout', [TenantAuthenticatedSessionController::class, 'destroy'])
        ->middleware('auth:tenant')
        ->name('tenant.logout');

    Route::post('/webhooks/inbound/{endpointPath}', InboundWebhookController::class)
        ->where('endpointPath', '.*')
        ->name('tenant.webhooks.inbound');

    Route::get('/', function () {
        return redirect()->route('tenant.admin.dashboard');
    })->middleware('auth:tenant')->name('tenant.dashboard');

    Route::middleware('auth:tenant')->name('tenant.admin.')->group(function (): void {
        Route::get('/dashboard', TenantAdminDashboardController::class)->name('dashboard');

        // Legacy routes kept for any external bookmarks/links; both areas now
        // live under Administration, consolidated rather than duplicated.
        Route::redirect('/users', '/administration/users')->name('users.index');
        Route::redirect('/settings', '/administration/workspace/general')->name('settings.edit');

        Route::prefix('administration')->name('administration.')->group(function (): void {
            Route::get('/', OverviewController::class)->name('index');

            Route::post('users/bulk-action', [AdministrationUserController::class, 'bulkAction'])->name('users.bulk-action');
            Route::post('users/{user}/activate', [AdministrationUserController::class, 'activate'])->name('users.activate');
            Route::post('users/{user}/suspend', [AdministrationUserController::class, 'suspend'])->name('users.suspend');
            Route::post('users/{user}/deactivate', [AdministrationUserController::class, 'deactivate'])->name('users.deactivate');
            Route::post('users/{user}/assign-roles', [AdministrationUserController::class, 'assignRoles'])->name('users.assign-roles');
            Route::resource('users', AdministrationUserController::class)->except(['destroy']);
            Route::delete('users/{user}', [AdministrationUserController::class, 'destroy'])->name('users.destroy');

            Route::post('invitations/{invitation}/resend', [InvitationController::class, 'resend'])->name('invitations.resend');
            Route::post('invitations/{invitation}/revoke', [InvitationController::class, 'revoke'])->name('invitations.revoke');
            Route::resource('invitations', InvitationController::class)->only(['index', 'create', 'store', 'destroy']);

            Route::post('teams/{team}/members', [TeamController::class, 'addMember'])->name('teams.members.store');
            Route::delete('teams/{team}/members/{user}', [TeamController::class, 'removeMember'])->name('teams.members.destroy');
            Route::post('teams/{team}/set-lead', [TeamController::class, 'setLead'])->name('teams.set-lead');
            Route::post('teams/{team}/archive', [TeamController::class, 'archive'])->name('teams.archive');
            Route::post('teams/{team}/restore', [TeamController::class, 'restore'])->name('teams.restore');
            Route::resource('teams', TeamController::class)->except(['destroy']);
            Route::delete('teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');

            Route::post('departments/{department}/archive', [DepartmentController::class, 'archive'])->name('departments.archive');
            Route::post('departments/{department}/restore', [DepartmentController::class, 'restore'])->name('departments.restore');
            Route::resource('departments', DepartmentController::class)->except(['show', 'destroy']);
            Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');

            Route::resource('roles', RoleController::class)->except(['show', 'destroy']);
            Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

            Route::get('workspace/{group?}', [WorkspaceSettingsController::class, 'edit'])->name('workspace.edit');
            Route::put('workspace/{group}', [WorkspaceSettingsController::class, 'update'])->name('workspace.update');

            Route::resource('business-hours', BusinessHourController::class)->except(['show']);
            Route::resource('holidays', HolidayController::class)->except(['show']);
        });

        Route::prefix('connections')->name('connections.')->group(function (): void {
            Route::get('', ConnectionsOverviewController::class)->name('overview');
            Route::get('all', [ConnectionController::class, 'index'])->name('index');
            Route::get('create', [ConnectionController::class, 'create'])->name('create');
            Route::post('/', [ConnectionController::class, 'store'])->name('store');

            Route::get('apps', [IntegrationCatalogController::class, 'index'])->name('apps.index');
            Route::get('apps/{integration:key}', [IntegrationCatalogController::class, 'show'])->name('apps.show');

            Route::get('data-sources', [DataSourceController::class, 'index'])->name('data-sources.index');
            Route::post('data-sources/{dataSource}/sync', [DataSourceController::class, 'sync'])->name('data-sources.sync');

            Route::get('api', [OperationsController::class, 'api'])->name('api.index');
            Route::get('databases', [OperationsController::class, 'databases'])->name('databases.index');
            Route::get('webhooks', [OperationsController::class, 'webhooks'])->name('webhooks.index');
            Route::post('webhooks/events/{event}/replay', [WebhookEventController::class, 'replay'])->name('webhooks.events.replay');
            Route::get('mcp', [OperationsController::class, 'mcp'])->name('mcp.index');
            Route::get('sync-jobs', [OperationsController::class, 'syncJobs'])->name('sync-jobs.index');
            Route::get('logs', [OperationsController::class, 'logs'])->name('logs.index');
            Route::get('failed', [OperationsController::class, 'failed'])->name('failed.index');
            Route::get('credentials', [OperationsController::class, 'credentials'])->name('credentials.index');
            Route::post('credentials/{credential}/rotate', [CredentialController::class, 'rotate'])->name('credentials.rotate');
            Route::post('credentials/{credential}/revoke', [CredentialController::class, 'revoke'])->name('credentials.revoke');
            Route::get('settings', [OperationsController::class, 'settings'])->name('settings.index');

            Route::post('{connection}/test', [ConnectionController::class, 'test'])->name('test');
            Route::post('{connection}/discover', [ConnectionController::class, 'discover'])->name('discover');
            Route::post('{connection}/sync', [ConnectionController::class, 'sync'])->name('sync');
            Route::post('{connection}/actions/{action}/execute', [ConnectionController::class, 'executeAction'])->name('actions.execute');
            Route::post('{connection}/disable', [ConnectionController::class, 'disable'])->name('disable');
            Route::post('{connection}/enable', [ConnectionController::class, 'enable'])->name('enable');
            Route::delete('{connection}', [ConnectionController::class, 'destroy'])->name('destroy');
            Route::get('{connection}', [ConnectionController::class, 'show'])->name('show');
        });

        /*
        |------------------------------------------------------------------
        | Knowledge Base
        |------------------------------------------------------------------
        |
        | Route model binding is deliberately NOT used here. Every knowledge
        | resource is addressed by UUID and resolved inside the controller
        | through ResolvesKnowledgeScope, which checks the actor's access
        | grants as part of the lookup — a record the user may not reach 404s
        | instead of loading and then being rejected.
        |
        */
        Route::prefix('knowledge')->name('knowledge.')->group(function (): void {
            Route::get('/', KnowledgeOverviewController::class)->name('index');

            // Static segments are registered before the {knowledgeBase}
            // wildcard so that /knowledge/bases/create is not swallowed by it.
            Route::prefix('bases')->name('bases.')->group(function (): void {
                Route::get('/', [KnowledgeBaseController::class, 'index'])->name('index');
                Route::get('create', [KnowledgeBaseController::class, 'create'])->name('create');
                Route::post('/', [KnowledgeBaseController::class, 'store'])->name('store');
                Route::get('{knowledgeBase}', [KnowledgeBaseController::class, 'show'])->name('show');
                Route::get('{knowledgeBase}/edit', [KnowledgeBaseController::class, 'edit'])->name('edit');
                Route::put('{knowledgeBase}', [KnowledgeBaseController::class, 'update'])->name('update');
                Route::get('{knowledgeBase}/impact', [KnowledgeBaseController::class, 'impact'])->name('impact');
                Route::post('{knowledgeBase}/archive', [KnowledgeBaseController::class, 'archive'])->name('archive');
                Route::post('{knowledgeBase}/restore', [KnowledgeBaseController::class, 'restore'])->name('restore');
                Route::post('{knowledgeBase}/reindex', [KnowledgeBaseController::class, 'reindex'])->name('reindex');
                Route::delete('{knowledgeBase}', [KnowledgeBaseController::class, 'destroy'])->name('destroy');
            });

            Route::prefix('documents')->name('documents.')->group(function (): void {
                Route::get('/', [KnowledgeDocumentController::class, 'index'])->name('index');
                Route::post('/', [KnowledgeDocumentController::class, 'store'])->name('store');
                Route::get('{document}', [KnowledgeDocumentController::class, 'show'])->name('show');
                // Signed: the URL is short-lived, and the policy is re-checked
                // inside the controller regardless of the signature.
                Route::get('{document}/download', [KnowledgeDocumentController::class, 'download'])
                    ->middleware('signed')
                    ->name('download');
                Route::post('{document}/reindex', [KnowledgeDocumentController::class, 'reindex'])->name('reindex');
                Route::delete('{document}', [KnowledgeDocumentController::class, 'destroy'])->name('destroy');
            });

            Route::prefix('websites')->name('websites.')->group(function (): void {
                Route::get('/', [KnowledgeWebsiteController::class, 'index'])->name('index');
                Route::post('/', [KnowledgeWebsiteController::class, 'store'])->name('store');
            });

            Route::prefix('faqs')->name('faqs.')->group(function (): void {
                Route::get('/', [KnowledgeFaqController::class, 'index'])->name('index');
                Route::post('/', [KnowledgeFaqController::class, 'store'])->name('store');
                Route::post('import', [KnowledgeFaqController::class, 'import'])->name('import');
                Route::put('{faq}', [KnowledgeFaqController::class, 'update'])->name('update');
                Route::post('{faq}/publish', [KnowledgeFaqController::class, 'publish'])->name('publish');
                Route::delete('{faq}', [KnowledgeFaqController::class, 'destroy'])->name('destroy');
            });

            Route::prefix('text-sources')->name('text-sources.')->group(function (): void {
                Route::get('/', [KnowledgeManualTextController::class, 'index'])->name('index');
                Route::post('/', [KnowledgeManualTextController::class, 'store'])->name('store');
            });

            Route::prefix('collections')->name('collections.')->group(function (): void {
                Route::get('/', [KnowledgeCollectionController::class, 'index'])->name('index');
                Route::post('/', [KnowledgeCollectionController::class, 'store'])->name('store');
                Route::put('{collection}', [KnowledgeCollectionController::class, 'update'])->name('update');
                Route::delete('{collection}', [KnowledgeCollectionController::class, 'destroy'])->name('destroy');
            });

            Route::prefix('sources')->name('sources.')->group(function (): void {
                Route::get('/', [KnowledgeSourceController::class, 'index'])->name('index');
                Route::get('{source}', [KnowledgeSourceController::class, 'show'])->name('show');
                Route::post('{source}/sync', [KnowledgeSourceController::class, 'sync'])->name('sync');
                Route::post('{source}/disable', [KnowledgeSourceController::class, 'disable'])->name('disable');
                Route::post('{source}/enable', [KnowledgeSourceController::class, 'enable'])->name('enable');
                Route::delete('{source}', [KnowledgeSourceController::class, 'destroy'])->name('destroy');
            });

            Route::get('processing', [KnowledgeOperationsController::class, 'processing'])->name('processing.index');
            Route::post('processing/{job}/cancel', [KnowledgeOperationsController::class, 'cancelJob'])->name('processing.cancel');

            Route::get('failed', [KnowledgeOperationsController::class, 'failures'])->name('failed.index');
            Route::get('failed/{failure}/details', [KnowledgeOperationsController::class, 'failureDetails'])->name('failed.details');
            Route::post('failed/{failure}/retry', [KnowledgeOperationsController::class, 'retryFailure'])->name('failed.retry');
            Route::post('failed/{failure}/dismiss', [KnowledgeOperationsController::class, 'dismissFailure'])->name('failed.dismiss');

            Route::get('playground', [KnowledgePlaygroundController::class, 'index'])->name('playground.index');
            Route::post('playground/retrieve', [KnowledgePlaygroundController::class, 'retrieve'])->name('playground.retrieve');

            Route::get('analytics', [KnowledgeOperationsController::class, 'analytics'])->name('analytics.index');
            Route::post('analytics/gaps/{gap}', [KnowledgeOperationsController::class, 'resolveGap'])->name('analytics.gaps.resolve');

            Route::get('settings', [KnowledgeSettingsController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [KnowledgeSettingsController::class, 'update'])->name('settings.update');
        });
    });

    Route::redirect('/tenant/admin', '/dashboard');
    Route::redirect('/tenant/admin/dashboard', '/dashboard');
    Route::get('/tenant/admin/{path}', fn (string $path) => redirect('/'.$path))
        ->where('path', '.*');

    Route::redirect('/tenant/dashboard', '/dashboard')
        ->middleware('auth:tenant')
        ->name('tenant.legacy-dashboard');
});
