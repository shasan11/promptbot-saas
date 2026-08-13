<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\PortalUser;
use App\Policies\Concerns\ChecksPortalMembership;

class PaymentPolicy
{
    use ChecksPortalMembership;

    public function view(PortalUser $user, Payment $payment): bool
    {
        if (! $payment->customerAccount || ! $this->hasCapability($user, $payment->customerAccount, 'can_manage_billing')) return false;
        if ($payment->tenant) return $this->hasWorkspaceAccess($user, $payment->tenant);
        if ($payment->invoice) return app(InvoicePolicy::class)->view($user, $payment->invoice);

        return false;
    }
}
