<?php

namespace App\Policies;

use App\Models\PortalUser;
use App\Models\SupportTicket;
use App\Policies\Concerns\ChecksPortalMembership;

class SupportTicketPolicy
{
    use ChecksPortalMembership;

    public function view(PortalUser $user, SupportTicket $ticket): bool
    {
        return $ticket->customerAccount && $this->hasCapability($user, $ticket->customerAccount, 'can_manage_support')
            && (! $ticket->tenant || $this->hasWorkspaceAccess($user, $ticket->tenant));
    }

    public function update(PortalUser $user, SupportTicket $ticket): bool { return $this->view($user, $ticket); }
}
