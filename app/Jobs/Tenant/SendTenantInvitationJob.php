<?php

namespace App\Jobs\Tenant;

use App\Jobs\Concerns\TenantAware;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Notifications\Tenant\TenantInvitationNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendTenantInvitationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TenantAware;

    public function __construct(
        private readonly int $invitationId,
        private readonly string $plainToken,
    ) {
        $this->captureTenant();
    }

    public function handle(): void
    {
        $invitation = TenantInvitation::find($this->invitationId);

        if (! $invitation || $invitation->status !== 'pending') {
            return;
        }

        $tenant = Tenant::find($this->tenantId);
        $domain = $tenant?->domains->firstWhere('is_primary', true)?->domain ?? $tenant?->domains->first()?->domain;

        if (! $domain) {
            return;
        }

        $scheme = config('app.env') === 'production' ? 'https' : 'http';
        $acceptUrl = "{$scheme}://{$domain}/invitation/{$this->plainToken}";

        (new AnonymousNotifiable)
            ->route('mail', $invitation->email)
            ->notify(new TenantInvitationNotification($invitation, $acceptUrl));

        $invitation->forceFill(['last_sent_at' => now()])->save();
    }
}
