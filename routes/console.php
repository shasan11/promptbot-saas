<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\WebsitePage;
use App\Models\BlogPost;
use App\Models\Subscription;
use App\Models\PortalNotification;
use App\Services\Platform\PortalNotificationService;
use App\Services\Platform\PlatformSettingsService;
use App\Services\Platform\SubscriptionLifecycleService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('cms:publish-scheduled', function (): void {
    $count = WebsitePage::query()->where('status', 'scheduled')->where('scheduled_at', '<=', now())
        ->update(['status' => 'published', 'published_at' => now(), 'scheduled_at' => null]);
    $posts = BlogPost::query()->where('status', 'scheduled')->where('scheduled_at', '<=', now())
        ->update(['status' => 'published', 'published_at' => now(), 'scheduled_at' => null]);
    $this->info("Published {$count} scheduled CMS page(s) and {$posts} blog post(s).");
})->purpose('Publish CMS pages whose scheduled time has arrived.');

Artisan::command('portal:send-subscription-reminders', function (PortalNotificationService $notifications, PlatformSettingsService $settings): void {
    $subscriptions = Subscription::query()->with(['customerAccount', 'tenant'])
        ->whereNotNull('customer_account_id')->whereIn('status', ['trial', 'active'])->get();
    $sent = 0;
    foreach ($subscriptions as $subscription) {
        $events = [];
        $trialDays = max(1, (int) $settings->get('trials', 'trial_ending_notice_days', 3));
        $renewalDays = max(1, (int) $settings->get('notifications', 'renewal_notice_days', 3));
        if ($subscription->status->value === 'trial' && $subscription->trial_ends_at?->between(now(), now()->addDays($trialDays))) {
            $events[] = ['subscription.trial_ending', 'Trial ending soon', "The trial for {$subscription->tenant?->company_name} ends on {$subscription->trial_ends_at->toDateString()}."];
        }
        if ($subscription->status->value === 'active' && $subscription->current_period_ends_at?->between(now(), now()->addDays($renewalDays))) {
            $events[] = ['subscription.renewal_upcoming', 'Subscription renewal approaching', "{$subscription->tenant?->company_name} renews on {$subscription->current_period_ends_at->toDateString()}."];
        }
        foreach ($events as [$type, $title, $body]) {
            $alreadySent = PortalNotification::where('customer_account_id', $subscription->customer_account_id)->where('type', $type)
                ->where('data->subscription_id', $subscription->getKey())->whereDate('created_at', now()->toDateString())->exists();
            if ($alreadySent) continue;
            $notifications->capability($subscription->customerAccount, 'can_manage_billing', $type, $title, $body,
                route('portal.billing.subscriptions', absolute: false), ['subscription_id' => $subscription->getKey()], $subscription->tenant_id);
            $sent++;
        }
    }
    $this->info("Dispatched {$sent} customer subscription reminder(s).");
})->purpose('Send customer trial-ending and subscription-renewal reminders.');

Artisan::command('subscriptions:process-lifecycle', function (SubscriptionLifecycleService $lifecycle): void {
    $result = $lifecycle->processDue();
    $this->info('Processed subscription lifecycle: '.collect($result)->map(fn ($count, $key) => "{$key}={$count}")->implode(', '));
})->purpose('Apply due subscription changes, cancellations, renewals, invoices, and past-due transitions.');

/*
|--------------------------------------------------------------------------
| Knowledge Base module
|--------------------------------------------------------------------------
|
| Both commands walk every active tenant, so they are guarded against overlap:
| a sweep that runs long must not have a second copy start behind it and
| double-dispatch the same sources.
|
*/

Schedule::command('knowledge:sync-sources')
    ->everyFifteenMinutes()
    ->withoutOverlapping(30)
    ->runInBackground();

Schedule::command('knowledge:release-stale-jobs')
    ->hourly()
    ->withoutOverlapping(10)
    ->runInBackground();

Schedule::command('inbox:release-snoozed')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground();

Schedule::command('sla:evaluate')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground();

Schedule::command('webhooks:deliver')->everyMinute()->withoutOverlapping(5)->runInBackground();
Schedule::command('cms:publish-scheduled')->everyMinute()->withoutOverlapping(5)->runInBackground();
Schedule::command('portal:send-subscription-reminders')->dailyAt('09:00')->withoutOverlapping(30)->runInBackground();
Schedule::command('subscriptions:process-lifecycle')->hourly()->withoutOverlapping(55)->runInBackground();
