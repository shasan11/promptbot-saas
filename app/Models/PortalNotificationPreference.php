<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class PortalNotificationPreference extends Model
{
    use CentralConnection;
    protected $guarded = [];
    protected $casts = ['billing_email' => 'boolean', 'workspace_email' => 'boolean', 'support_email' => 'boolean', 'security_email' => 'boolean', 'product_email' => 'boolean', 'event_channels' => 'array'];

    public function channelEnabled(string $event, string $channel): bool
    {
        $configured = data_get($this->event_channels, "{$event}.{$channel}");
        if ($configured !== null) return (bool) $configured;
        return match ($event) {
            'workspace_provisioned', 'workspace_suspended' => $channel === 'in_app' || $this->workspace_email,
            'support_ticket_reply' => $channel === 'in_app' || $this->support_email,
            default => $channel === 'in_app' || $this->billing_email,
        };
    }
}
