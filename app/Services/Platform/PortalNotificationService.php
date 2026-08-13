<?php

namespace App\Services\Platform;

use App\Models\CustomerAccount;
use App\Models\PortalNotification;
use App\Models\PortalNotificationPreference;
use App\Notifications\Portal\PlatformEventNotification;

class PortalNotificationService
{
    public function __construct(private readonly PlatformSettingsService $settings) {}

    public function account(CustomerAccount|int $account, string $type, string $title, ?string $body = null, ?string $url = null, ?int $portalUserId = null, array $data = []): PortalNotification
    {
        return PortalNotification::create([
            'customer_account_id' => $account instanceof CustomerAccount ? $account->getKey() : $account,
            'portal_user_id' => $portalUserId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'data' => $data ?: null,
        ]);
    }

    public function capability(CustomerAccount|int $account, string $capability, string $type, string $title, ?string $body = null, ?string $url = null, array $data = [], ?string $tenantId = null): void
    {
        if (! $this->eventEnabled($type)) return;

        $account = $account instanceof CustomerAccount ? $account : CustomerAccount::query()->findOrFail($account);
        $recipients = $account->users()->where(function ($query) use ($capability): void {
            $roles = $capability === 'can_manage_billing' ? ['owner', 'admin', 'billing'] : ['owner', 'admin'];
            $query->whereIn('customer_account_users.role', $roles)
                ->orWhere('customer_account_users.'.$capability, true);
        });
        if ($tenantId) {
            $recipients->where(function ($query) use ($account, $tenantId): void {
                $query->where('customer_account_users.role', 'owner')
                    ->orWhere('customer_account_users.service_access', '!=', 'selected')
                    ->orWhereExists(fn ($grant) => $grant->selectRaw('1')->from('customer_account_user_tenants')
                        ->whereColumn('customer_account_user_tenants.portal_user_id', 'portal_users.id')
                        ->where('customer_account_user_tenants.customer_account_id', $account->getKey())
                        ->where('customer_account_user_tenants.tenant_id', $tenantId));
            });
        }
        $event = $this->eventKey($type);
        $template = $this->templateKey($type);
        $values = [...$data, 'account_name' => $account->name];
        if ($tenantId) $values['workspace_name'] = $account->tenants()->whereKey($tenantId)->value('company_name') ?? '';

        $recipients->get()->each(function ($recipient) use ($account, $event, $template, $type, $title, $body, $url, $values): void {
            $preference = PortalNotificationPreference::firstOrCreate(
                ['portal_user_id' => $recipient->getKey(), 'customer_account_id' => $account->getKey()]
            );
            if ($preference->channelEnabled($event, 'in_app')) {
                $this->account($account, $type, $title, $body, $url, (int) $recipient->getKey(), $values);
            }
            if ($preference->channelEnabled($event, 'email')) {
                try {
                    $recipient->notify(new PlatformEventNotification($template, $title, $body, $url, $values));
                } catch (\Throwable $exception) {
                    report($exception);
                }
            }
        });
    }

    private function eventEnabled(string $type): bool
    {
        $key = match (true) {
            str_starts_with($type, 'billing.'), str_starts_with($type, 'subscription.') => 'billing_events_enabled',
            str_starts_with($type, 'workspace.') => 'workspace_events_enabled',
            str_starts_with($type, 'support.') => 'support_events_enabled',
            default => null,
        };

        return $key === null || filter_var($this->settings->get('notifications', $key, true), FILTER_VALIDATE_BOOL);
    }

    private function eventKey(string $type): string
    {
        return match ($type) {
            'billing.invoice_issued' => 'invoice_issued',
            'billing.payment_received' => 'payment_successful',
            'billing.payment_failed' => 'payment_failed',
            'subscription.renewed', 'subscription.renewal_upcoming' => 'subscription_renewal',
            'subscription.trial_ending' => 'trial_ending',
            'workspace.ready' => 'workspace_provisioned',
            'workspace.suspended' => 'workspace_suspended',
            'support.reply' => 'support_ticket_reply',
            default => str_replace('.', '_', $type),
        };
    }

    private function templateKey(string $type): string
    {
        return match ($type) {
            'billing.invoice_issued' => 'invoice_issued',
            'billing.payment_received' => 'payment_received',
            'billing.payment_failed' => 'payment_failed',
            'subscription.trial_ending' => 'trial_ending',
            'subscription.cancelled' => 'subscription_cancelled',
            'subscription.renewed', 'subscription.renewal_upcoming' => 'subscription_renewal',
            'workspace.ready' => 'workspace_provisioned',
            'workspace.suspended' => 'workspace_suspended',
            'support.reply' => 'support_update',
            default => 'welcome',
        };
    }
}
