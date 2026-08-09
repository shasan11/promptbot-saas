<?php

use App\Http\Middleware\EnforceTenantLimit;
use App\Http\Middleware\EnsureCentralDomain;
use App\Http\Middleware\EnsureCentralUserIsActive;
use App\Http\Middleware\EnsureCentralUserPasswordIsValid;
use App\Http\Middleware\EnsureInstallerIsOpen;
use App\Http\Middleware\EnsureTenancyIsInitialized;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireTenantFeature;
use App\Http\Middleware\AuthenticateDeveloperApiKey;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('web')
                ->group(base_path('routes/admin.php'));

            Route::middleware('api')
                ->prefix('tenant-api')
                ->group(base_path('routes/tenant-api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'widget/api/*',
            'channels/email/*/inbound',
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'central.domain' => EnsureCentralDomain::class,
            'central.active' => EnsureCentralUserIsActive::class,
            'central.password' => EnsureCentralUserPasswordIsValid::class,
            'installer.open' => EnsureInstallerIsOpen::class,
            'tenant.active' => EnsureTenantIsActive::class,
            'tenant.initialized' => EnsureTenancyIsInitialized::class,
            'tenant.feature' => RequireTenantFeature::class,
            'tenant.limit' => EnforceTenantLimit::class,
            'permission' => PermissionMiddleware::class,
            'developer.key' => AuthenticateDeveloperApiKey::class,
        ]);

        // An unauthenticated visitor on a tenant subdomain must be sent to
        // that tenant's own login page, never the central admin login.
        $middleware->redirectGuestsTo(fn (Request $request) => tenancy()->initialized
            ? route('tenant.login')
            : route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TenantCouldNotBeIdentifiedOnDomainException $exception, Request $request) {
            return response('', 404);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
