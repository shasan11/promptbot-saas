<?php

namespace App\Providers;

use App\Contracts\TenantDatabaseProvisioner;
use App\Services\Platform\PlatformSettingsService;
use App\Services\Tenancy\TenantDatabaseProvisionerFactory;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Facades\Event;
use App\Events\Inbox\ConversationReceived;
use App\Listeners\AI\QueueConversationAnalysis;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PlatformSettingsService::class);

        $this->app->bind(TenantDatabaseProvisioner::class, function ($app) {
            return $app->make(TenantDatabaseProvisionerFactory::class)->make();
        });
    }

    public function boot(PlatformSettingsService $settings): void
    {
        $settings->apply();
        Event::listen(ConversationReceived::class, QueueConversationAnalysis::class);
        Vite::prefetch(concurrency: 3);
    }
}
