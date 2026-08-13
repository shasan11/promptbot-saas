<?php

namespace App\Services\Platform;

use App\Models\CustomerAccount;
use App\Models\CustomerAccountActivity;
use App\Models\CustomerAccountInvitation;
use App\Models\PortalUser;
use App\Notifications\Portal\AccountInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class AccountInvitationService
{
    public function invite(CustomerAccount $account, PortalUser $inviter, array $data): CustomerAccountInvitation
    {
        $token = Str::random(64);
        $invitation = DB::transaction(function () use ($account, $inviter, $data, $token): CustomerAccountInvitation {
            $invitation = CustomerAccountInvitation::updateOrCreate(
                ['customer_account_id' => $account->getKey(), 'email' => strtolower($data['email'])],
                [
                    ...collect($data)->except('email')->all(),
                    'token_hash' => hash('sha256', $token),
                    'invited_by' => $inviter->getKey(),
                    'expires_at' => now()->addDays(7),
                    'accepted_at' => null,
                ]
            );
            CustomerAccountActivity::create([
                'customer_account_id' => $account->getKey(), 'actor_type' => PortalUser::class,
                'actor_id' => (string) $inviter->getKey(), 'event' => 'member.invited',
                'description' => "{$data['email']} was invited to the account.",
            ]);
            return $invitation;
        });

        $url = route('portal.invitations.accept', [$invitation, $token]);
        $existing = PortalUser::where('email', strtolower($data['email']))->first();
        $existing
            ? $existing->notify(new AccountInvitationNotification($account, $url))
            : Notification::route('mail', $data['email'])->notify(new AccountInvitationNotification($account, $url));

        return $invitation;
    }
}
