<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\PortalUser;
use App\Policies\Concerns\ChecksPortalMembership;

class InvoicePolicy
{
    use ChecksPortalMembership;

    public function view(PortalUser $user, Invoice $invoice): bool
    {
        if (! $invoice->customerAccount || ! $this->hasCapability($user, $invoice->customerAccount, 'can_manage_billing')) {
            return false;
        }
        if ($invoice->tenant) {
            return $this->hasWorkspaceAccess($user, $invoice->tenant);
        }
        return $invoice->items()->whereNotNull('tenant_id')->get()->every(fn ($item) => $this->hasWorkspaceAccess($user, $item->tenant));
    }
}
