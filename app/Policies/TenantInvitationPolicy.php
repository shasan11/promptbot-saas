<?php

namespace App\Policies;

use App\Models\TenantInvitation;
use App\Models\User;

class TenantInvitationPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('invitations.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('invitations.create');
    }

    public function resend(User $actor, TenantInvitation $invitation): bool
    {
        return $actor->can('invitations.resend');
    }

    public function revoke(User $actor, TenantInvitation $invitation): bool
    {
        return $actor->can('invitations.revoke');
    }
}
