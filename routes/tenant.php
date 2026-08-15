<?php

declare(strict_types=1);

use App\Http\Controllers\LegacyPathRedirectController;
use App\Http\Controllers\Tenant\Admin\Administration\BusinessHourController;
use App\Http\Controllers\Tenant\Admin\Administration\DepartmentController;
use App\Http\Controllers\Tenant\Admin\Administration\HolidayController;
use App\Http\Controllers\Tenant\Admin\Administration\InvitationController;
use App\Http\Controllers\Tenant\Admin\Administration\OverviewController;
use App\Http\Controllers\Tenant\Admin\Administration\RoleController;
use App\Http\Controllers\Tenant\Admin\Administration\TeamController;
use App\Http\Controllers\Tenant\Admin\Administration\UserController as AdministrationUserController;
use App\Http\Controllers\Tenant\Admin\Administration\WorkspaceSettingsController;
use App\Http\Controllers\Tenant\Admin\AI\OverviewController as AIOverviewController;
use App\Http\Controllers\Tenant\Admin\AI\AgentController as AIAgentController;
use App\Http\Controllers\Tenant\Admin\AI\PlaygroundController as AIPlaygroundController;
use App\Http\Controllers\Tenant\Admin\AI\PromptController as AIPromptController;
use App\Http\Controllers\Tenant\Admin\AI\ApprovalController as AIApprovalController;
use App\Http\Controllers\Tenant\Admin\AI\OperationsController as AIOperationsController;
use App\Http\Controllers\Tenant\Admin\AI\EvaluationController as AIEvaluationController;
use App\Http\Controllers\Tenant\Admin\AI\InboxCopilotController as AIInboxCopilotController;
use App\Http\Controllers\Tenant\Admin\AI\TicketCopilotController as AITicketCopilotController;
use App\Http\Controllers\Tenant\Admin\AI\ReportInsightController as AIReportInsightController;
use App\Http\Controllers\Tenant\Admin\AI\CustomerBriefController as AICustomerBriefController;
use App\Http\Controllers\Tenant\Admin\AI\ProviderController as AIProviderController;
use App\Http\Controllers\Tenant\Admin\AI\SettingsController as AISettingsController;
use App\Http\Controllers\Tenant\Admin\Automation\AutomationController;
use App\Http\Controllers\Tenant\Admin\Channel\ChannelController;
use App\Http\Controllers\Tenant\Admin\Connections\ApiOperationController;
use App\Http\Controllers\Tenant\Admin\Connections\ConnectionController;
use App\Http\Controllers\Tenant\Admin\Connections\CredentialController;
use App\Http\Controllers\Tenant\Admin\Connections\DatabaseConfigController;
use App\Http\Controllers\Tenant\Admin\Connections\DataSourceController;
use App\Http\Controllers\Tenant\Admin\Connections\IntegrationCatalogController;
use App\Http\Controllers\Tenant\Admin\Connections\McpToolController;
use App\Http\Controllers\Tenant\Admin\Connections\OAuthAuthorizationController;
use App\Http\Controllers\Tenant\Admin\Connections\OperationsController;
use App\Http\Controllers\Tenant\Admin\Connections\OverviewController as ConnectionsOverviewController;
use App\Http\Controllers\Tenant\Admin\Connections\PermissionController;
use App\Http\Controllers\Tenant\Admin\Connections\SyncRunController;
use App\Http\Controllers\Tenant\Admin\Connections\WebhookEventController;
use App\Http\Controllers\Tenant\Admin\Customer\CompanyController;
use App\Http\Controllers\Tenant\Admin\Customer\ContactController;
use App\Http\Controllers\Tenant\Admin\Customer\CustomerOverviewController;
use App\Http\Controllers\Tenant\Admin\Customer\CustomerImportController;
use App\Http\Controllers\Tenant\Admin\Customer\CustomFieldController;
use App\Http\Controllers\Tenant\Admin\Customer\TagController;
use App\Http\Controllers\Tenant\Admin\DashboardController as TenantAdminDashboardController;
use App\Http\Controllers\Tenant\Admin\WorkspaceEntryController;
use App\Http\Controllers\Tenant\Admin\Experience\ExperienceController;
use App\Http\Controllers\Tenant\Admin\Governance\GovernanceController;
use App\Http\Controllers\Tenant\Admin\Inbox\AttachmentController;
use App\Http\Controllers\Tenant\Admin\Inbox\ConversationController;
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
use App\Http\Controllers\Tenant\Admin\NotificationController;
use App\Http\Controllers\Tenant\Admin\Operations\OperationsController as SupportOperationsController;
use App\Http\Controllers\Tenant\Admin\ProfileController;
use App\Http\Controllers\Tenant\Admin\Quality\QualityWorkforceController;
use App\Http\Controllers\Tenant\Admin\Reporting\ReportingController;
use App\Http\Controllers\Tenant\Admin\SearchController;
use App\Http\Controllers\Tenant\Admin\Task\TaskController;
use App\Http\Controllers\Tenant\Admin\Ticket\TicketController;
use App\Http\Controllers\Tenant\Admin\Ticket\TicketSettingsController;
use App\Http\Controllers\Tenant\Auth\TenantAuthenticatedSessionController;
use App\Http\Controllers\Tenant\Channel\InboundEmailController;
use App\Http\Controllers\Tenant\Connections\InboundWebhookController;
use App\Http\Controllers\Tenant\InvitationAcceptController;
use App\Http\Controllers\Tenant\PublicSupportController;
use App\Http\Controllers\Tenant\Widget\WebChatController;
use App\Http\Controllers\Tenant\Widget\WidgetCorsController;
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

    Route::post('/tenant/logout', [TenantAuthenticatedSessionController::class, 'destroy'])
        ->middleware('auth:tenant')
        ->name('tenant.logout');

    Route::post('/webhooks/inbound/{endpointPath}', InboundWebhookController::class)
        ->where('endpointPath', '.*')
        ->name('tenant.webhooks.inbound');

    Route::get('/widget/promptbot.js', [WebChatController::class, 'script'])->name('tenant.widget.script');
    Route::get('/widget/api/{key}/config', [WebChatController::class, 'config'])->middleware('throttle:120,1')->name('tenant.widget.config');
    Route::post('/widget/api/{key}/session', [WebChatController::class, 'session'])->middleware('throttle:20,1')->name('tenant.widget.session');
    Route::get('/widget/api/{key}/messages', [WebChatController::class, 'poll'])->middleware('throttle:120,1')->name('tenant.widget.messages.poll');
    Route::post('/widget/api/{key}/messages', [WebChatController::class, 'messages'])->middleware('throttle:60,1')->name('tenant.widget.messages.store');
    Route::options('/widget/api/{key}/{path?}', WidgetCorsController::class)->where('path', '.*');
    Route::post('/channels/email/{channel}/inbound', InboundEmailController::class)->middleware('throttle:120,1')->name('tenant.channels.email.inbound');
    Route::get('/help', [PublicSupportController::class, 'help'])->middleware('throttle:120,1')->name('tenant.help');
    Route::get('/forms/{slug}', [PublicSupportController::class, 'form'])->middleware('throttle:120,1')->name('tenant.forms.show');
    Route::post('/forms/{slug}', [PublicSupportController::class, 'submit'])->middleware('throttle:10,1')->name('tenant.forms.submit');
    Route::post('/csat/{survey}/{resourceType}/{resourceId}', [PublicSupportController::class, 'csat'])->middleware(['signed', 'throttle:10,1'])->name('tenant.csat.submit');
    Route::get('/portal/{token}', [PublicSupportController::class, 'portal'])->middleware('throttle:30,1')->name('tenant.portal');

    Route::middleware('auth:tenant')->name('tenant.admin.')->group(function (): void {
        Route::get('/dashboard', TenantAdminDashboardController::class)->name('dashboard');
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/password', [ProfileController::class, 'password'])->name('profile.password');
        Route::get('/search', SearchController::class)->name('search');
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::put('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::put('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
        Route::put('/notification-preferences', [NotificationController::class, 'preference'])->name('notifications.preferences');

        Route::get('/engagement', [WorkspaceEntryController::class, 'engagement'])->name('engagement.entry');
        Route::get('/operations-workspace', [WorkspaceEntryController::class, 'operations'])->name('operations.entry');
        Route::get('/platform', [WorkspaceEntryController::class, 'platform'])->name('platform.entry');

        Route::prefix('customers')->name('customers.')->group(function (): void {
            Route::get('/', CustomerOverviewController::class)->name('index');
            Route::post('contacts/bulk', [ContactController::class, 'bulk'])->name('contacts.bulk');
            Route::post('contacts/{contact}/restore', [ContactController::class, 'restore'])->name('contacts.restore');
            Route::post('contacts/{contact}/ai-brief', [AICustomerBriefController::class, 'contact'])->middleware(['tenant.feature:ai_platform', 'throttle:10,60'])->name('contacts.ai-brief');
            Route::resource('contacts', ContactController::class);

            Route::post('companies/{company}/restore', [CompanyController::class, 'restore'])->name('companies.restore');
            Route::post('companies/{company}/ai-brief', [AICustomerBriefController::class, 'company'])->middleware(['tenant.feature:ai_platform', 'throttle:10,60'])->name('companies.ai-brief');
            Route::resource('companies', CompanyController::class);

            Route::get('tags', [TagController::class, 'index'])->name('tags.index');
            Route::post('tags', [TagController::class, 'store'])->name('tags.store');
            Route::put('tags/{tag}', [TagController::class, 'update'])->name('tags.update');
            Route::post('tags/{tag}/merge', [TagController::class, 'merge'])->name('tags.merge');

            Route::get('custom-fields', [CustomFieldController::class, 'index'])->name('custom-fields.index');
            Route::post('custom-fields', [CustomFieldController::class, 'store'])->name('custom-fields.store');
            Route::put('custom-fields/{customField}', [CustomFieldController::class, 'update'])->name('custom-fields.update');
            Route::delete('custom-fields/{customField}', [CustomFieldController::class, 'destroy'])->name('custom-fields.destroy');

            Route::get('imports', [CustomerImportController::class, 'index'])->name('imports.index');
            Route::post('imports', [CustomerImportController::class, 'store'])->name('imports.store');
            Route::get('exports/contacts', [CustomerImportController::class, 'export'])->name('exports.contacts');
        });

        Route::resource('channels', ChannelController::class);
        Route::post('channels/{channel}/rotate-inbound-secret', [ChannelController::class, 'rotateInboundSecret'])->name('channels.rotate-inbound-secret');

        Route::prefix('inbox')->name('inbox.')->group(function (): void {
            Route::get('/', [ConversationController::class, 'index'])->name('index');
            Route::get('{conversation}', [ConversationController::class, 'show'])->name('show');
            Route::post('{conversation}/reply', [ConversationController::class, 'reply'])->name('reply');
            Route::put('{conversation}', [ConversationController::class, 'update'])->name('update');
            Route::post('{conversation}/assign', [ConversationController::class, 'assign'])->name('assign');
            Route::post('{conversation}/follow', [ConversationController::class, 'follow'])->name('follow');
            Route::post('{conversation}/ai', [AIInboxCopilotController::class, 'run'])->middleware(['tenant.feature:ai_platform', 'throttle:20,1'])->name('ai.run');
            Route::post('messages/{message}/attachments', [AttachmentController::class, 'store'])->name('attachments.store');
            Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download'])->middleware('signed')->name('attachments.download');
        });

        Route::post('tickets/{ticket}/ai', [AITicketCopilotController::class, 'run'])->middleware(['tenant.feature:ai_platform', 'throttle:20,1'])->name('tickets.ai.run');
        Route::post('tickets/{ticket}/comments', [TicketController::class, 'comment'])->name('tickets.comments.store');
        Route::post('tickets/{ticket}/merge', [TicketController::class, 'merge'])->name('tickets.merge');
        Route::get('tickets/settings/configuration', [TicketSettingsController::class, 'index'])->name('tickets.settings.index');
        Route::post('tickets/settings/statuses', [TicketSettingsController::class, 'storeStatus'])->name('tickets.settings.statuses.store');
        Route::put('tickets/settings/statuses/{status}', [TicketSettingsController::class, 'updateStatus'])->name('tickets.settings.statuses.update');
        Route::post('tickets/settings/categories', [TicketSettingsController::class, 'storeCategory'])->name('tickets.settings.categories.store');
        Route::put('tickets/settings/categories/{category}', [TicketSettingsController::class, 'updateCategory'])->name('tickets.settings.categories.update');
        Route::resource('tickets', TicketController::class);
        Route::resource('tasks', TaskController::class);

        Route::get('operations', [SupportOperationsController::class, 'index'])->name('operations.index');
        Route::post('operations/queues', [SupportOperationsController::class, 'storeQueue'])->name('operations.queues.store');
        Route::put('operations/presence/{user}', [SupportOperationsController::class, 'updatePresence'])->name('operations.presence.update');
        Route::post('operations/rules', [SupportOperationsController::class, 'storeRule'])->name('operations.rules.store');
        Route::post('operations/sla', [SupportOperationsController::class, 'storeSla'])->name('operations.sla.store');
        Route::post('operations/escalations', [SupportOperationsController::class, 'storeEscalation'])->name('operations.escalations.store');

        Route::get('automation', [AutomationController::class, 'index'])->name('automation.index');
        Route::post('automation/saved-replies', [AutomationController::class, 'storeReply'])->name('automation.saved-replies.store');
        Route::delete('automation/saved-replies/{savedReply}', [AutomationController::class, 'destroyReply'])->name('automation.saved-replies.destroy');
        Route::post('automation/macros', [AutomationController::class, 'storeMacro'])->name('automation.macros.store');
        Route::delete('automation/macros/{macro}', [AutomationController::class, 'destroyMacro'])->name('automation.macros.destroy');
        Route::post('automation/rules', [AutomationController::class, 'storeRule'])->name('automation.rules.store');
        Route::put('automation/rules/{automationRule}/toggle', [AutomationController::class, 'toggle'])->name('automation.rules.toggle');

        Route::get('experience', [ExperienceController::class, 'index'])->name('experience.index');
        Route::post('experience/segments', [ExperienceController::class, 'storeSegment'])->name('experience.segments.store');
        Route::post('experience/lists', [ExperienceController::class, 'storeList'])->name('experience.lists.store');
        Route::post('experience/forms', [ExperienceController::class, 'storeForm'])->name('experience.forms.store');
        Route::put('experience/help-center', [ExperienceController::class, 'updateHelpCenter'])->name('experience.help-center.update');
        Route::post('experience/csat', [ExperienceController::class, 'storeSurvey'])->name('experience.csat.store');
        Route::post('experience/portal-link', [ExperienceController::class, 'portalLink'])->name('experience.portal-link');
        Route::get('reports', [ReportingController::class, 'index'])->name('reports.index');
        Route::get('reports/export', [ReportingController::class, 'export'])->name('reports.export');
        Route::post('reports/ai-insight', AIReportInsightController::class)->middleware(['tenant.feature:ai_platform', 'throttle:10,60'])->name('reports.ai-insight');
        Route::get('developer', [GovernanceController::class, 'index'])->name('governance.index');
        Route::post('developer/api-keys', [GovernanceController::class, 'createKey'])->name('governance.api-keys.store');
        Route::put('developer/api-keys/{key}/revoke', [GovernanceController::class, 'revokeKey'])->name('governance.api-keys.revoke');
        Route::post('developer/webhooks', [GovernanceController::class, 'createWebhook'])->name('governance.webhooks.store');
        Route::post('developer/retention', [GovernanceController::class, 'saveRetention'])->name('governance.retention.store');
        Route::post('developer/privacy', [GovernanceController::class, 'createPrivacy'])->name('governance.privacy.store');
        Route::get('quality', [QualityWorkforceController::class, 'quality'])->name('quality.index');
        Route::post('quality/scorecards', [QualityWorkforceController::class, 'storeScorecard'])->name('quality.scorecards.store');
        Route::post('quality/reviews', [QualityWorkforceController::class, 'storeReview'])->name('quality.reviews.store');
        Route::get('workforce', [QualityWorkforceController::class, 'workforce'])->name('workforce.index');
        Route::post('workforce/shifts', [QualityWorkforceController::class, 'storeShift'])->name('workforce.shifts.store');
        Route::post('workforce/time-off', [QualityWorkforceController::class, 'requestTimeOff'])->name('workforce.time-off.store');

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
            Route::post('data-sources/{dataSource}/database-config', [DatabaseConfigController::class, 'store'])->name('data-sources.database-config.store');

            Route::get('api', [OperationsController::class, 'api'])->name('api.index');
            Route::get('databases', [OperationsController::class, 'databases'])->name('databases.index');
            Route::get('webhooks', [OperationsController::class, 'webhooks'])->name('webhooks.index');
            Route::post('webhooks/events/{event}/replay', [WebhookEventController::class, 'replay'])->name('webhooks.events.replay');
            Route::get('mcp', [OperationsController::class, 'mcp'])->name('mcp.index');
            Route::get('sync-jobs', [OperationsController::class, 'syncJobs'])->name('sync-jobs.index');
            Route::post('sync-jobs/{syncRun}/retry', [SyncRunController::class, 'retry'])->name('sync-jobs.retry');
            Route::get('logs', [OperationsController::class, 'logs'])->name('logs.index');
            Route::get('failed', [OperationsController::class, 'failed'])->name('failed.index');
            Route::get('credentials', [OperationsController::class, 'credentials'])->name('credentials.index');
            Route::post('credentials/{credential}/rotate', [CredentialController::class, 'rotate'])->name('credentials.rotate');
            Route::post('credentials/{credential}/revoke', [CredentialController::class, 'revoke'])->name('credentials.revoke');
            Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
            Route::post('{connection}/permissions/agents', [PermissionController::class, 'storeAgent'])->name('permissions.agents.store');
            Route::post('{connection}/permissions/workflows', [PermissionController::class, 'storeWorkflow'])->name('permissions.workflows.store');
            Route::post('{connection}/permissions/access-grants', [PermissionController::class, 'storeAccessGrant'])->name('permissions.access-grants.store');
            Route::post('resources/{resource}/permissions', [PermissionController::class, 'storeResourceGrant'])->name('permissions.resources.store');
            Route::delete('permissions/agents/{grant}', [PermissionController::class, 'destroyAgent'])->name('permissions.agents.destroy');
            Route::delete('permissions/workflows/{grant}', [PermissionController::class, 'destroyWorkflow'])->name('permissions.workflows.destroy');
            Route::delete('permissions/access-grants/{grant}', [PermissionController::class, 'destroyAccessGrant'])->name('permissions.access-grants.destroy');
            Route::delete('resource-permissions/{grant}', [PermissionController::class, 'destroyResourceGrant'])->name('permissions.resources.destroy');
            Route::get('settings', [OperationsController::class, 'settings'])->name('settings.index');
            Route::post('oauth/start', [OAuthAuthorizationController::class, 'start'])->name('oauth.start');
            Route::get('oauth/callback', [OAuthAuthorizationController::class, 'callback'])->name('oauth.callback');

            Route::post('{connection}/test', [ConnectionController::class, 'test'])->name('test');
            Route::post('{connection}/discover', [ConnectionController::class, 'discover'])->name('discover');
            Route::post('{connection}/sync', [ConnectionController::class, 'sync'])->name('sync');
            Route::post('{connection}/reconnect', [ConnectionController::class, 'reconnect'])->name('reconnect');
            Route::post('{connection}/retry-failed-sync', [ConnectionController::class, 'retryFailedSync'])->name('retry-failed-sync');
            Route::post('{connection}/api-operations', [ApiOperationController::class, 'store'])->name('api-operations.store');
            Route::delete('api-operations/{operation}', [ApiOperationController::class, 'destroy'])->name('api-operations.destroy');
            Route::post('{connection}/mcp-tools', [McpToolController::class, 'store'])->name('mcp-tools.store');
            Route::put('{connection}/mcp-tools/{tool}', [McpToolController::class, 'update'])->name('mcp-tools.update');
            Route::post('{connection}/actions/{action}/execute', [ConnectionController::class, 'executeAction'])->name('actions.execute');
            Route::post('{connection}/disable', [ConnectionController::class, 'disable'])->name('disable');
            Route::post('{connection}/enable', [ConnectionController::class, 'enable'])->name('enable');
            Route::get('{connection}/edit', [ConnectionController::class, 'edit'])->name('edit');
            Route::put('{connection}', [ConnectionController::class, 'update'])->name('update');
            Route::delete('{connection}', [ConnectionController::class, 'destroy'])->name('destroy');
            Route::get('{connection}', [ConnectionController::class, 'show'])->name('show');
        });

        Route::prefix('ai')->name('ai.')->middleware('tenant.feature:ai_platform')->group(function (): void {
            Route::get('/', AIOverviewController::class)->name('index');
            Route::get('agents', [AIAgentController::class, 'index'])->name('agents.index');
            Route::post('agents', [AIAgentController::class, 'store'])->name('agents.store');
            Route::put('agents/{agent}', [AIAgentController::class, 'update'])->name('agents.update');
            Route::post('agents/{agent}/deploy', [AIAgentController::class, 'deploy'])->name('agents.deploy');
            Route::post('agents/{agent}/pause', [AIAgentController::class, 'pause'])->name('agents.pause');
            Route::post('agents/{agent}/versions/{version}/restore', [AIAgentController::class, 'restore'])->name('agents.versions.restore');
            Route::put('agents/{agent}/tools', [AIAgentController::class, 'updateTools'])->name('agents.tools.update');
            Route::put('agents/{agent}/channels', [AIAgentController::class, 'updateChannels'])->name('agents.channels.update');
            Route::get('playground', [AIPlaygroundController::class, 'index'])->name('playground.index');
            Route::post('playground', [AIPlaygroundController::class, 'run'])->middleware('throttle:15,1')->name('playground.run');
            Route::post('playground/stream', [AIPlaygroundController::class, 'stream'])->middleware('throttle:15,1')->name('playground.stream');
            Route::post('playground/cancel', [AIPlaygroundController::class, 'cancel'])->middleware('throttle:30,1')->name('playground.cancel');
            Route::get('prompts', [AIPromptController::class, 'index'])->name('prompts.index');
            Route::post('prompts', [AIPromptController::class, 'store'])->name('prompts.store');
            Route::put('prompts/{prompt}', [AIPromptController::class, 'update'])->name('prompts.update');
            Route::post('prompts/{prompt}/publish', [AIPromptController::class, 'publish'])->name('prompts.publish');
            Route::get('approvals', [AIApprovalController::class, 'index'])->name('approvals.index');
            Route::post('approvals/{approval}/approve', [AIApprovalController::class, 'approve'])->name('approvals.approve');
            Route::post('approvals/{approval}/reject', [AIApprovalController::class, 'reject'])->name('approvals.reject');
            Route::get('usage', [AIOperationsController::class, 'usage'])->name('usage.index');
            Route::get('logs', [AIOperationsController::class, 'logs'])->name('logs.index');
            Route::get('evaluations', [AIEvaluationController::class, 'index'])->name('evaluations.index');
            Route::post('evaluations', [AIEvaluationController::class, 'storeSuite'])->name('evaluations.store');
            Route::post('evaluations/{suite}/cases', [AIEvaluationController::class, 'storeCase'])->name('evaluations.cases.store');
            Route::post('evaluations/{suite}/run', [AIEvaluationController::class, 'run'])->middleware('throttle:5,60')->name('evaluations.run');
            Route::get('providers', [AIProviderController::class, 'index'])->name('providers.index');
            Route::post('providers', [AIProviderController::class, 'store'])->name('providers.store');
            Route::put('providers/{provider}', [AIProviderController::class, 'update'])->name('providers.update');
            Route::post('providers/{provider}/test', [AIProviderController::class, 'test'])
                ->middleware('throttle:10,60')->name('providers.test');
            Route::delete('providers/{provider}', [AIProviderController::class, 'destroy'])->name('providers.destroy');
            Route::get('settings', [AISettingsController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [AISettingsController::class, 'update'])->name('settings.update');
            Route::post('suggestions/{suggestion}/feedback', [AIInboxCopilotController::class, 'feedback'])->name('suggestions.feedback');
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
    Route::get('/tenant/admin/{path}', [LegacyPathRedirectController::class, 'tenant'])
        ->where('path', '.*');

    Route::redirect('/tenant/dashboard', '/dashboard')
        ->middleware('auth:tenant')
        ->name('tenant.legacy-dashboard');
});
