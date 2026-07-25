<?php

namespace App\Services\Platform;

use App\Models\CentralUser;
use App\Models\PlatformAdminInvitation;
use App\Models\PlatformRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdministratorInvitationService
{
    public function invite(CentralUser $inviter, string $email, PlatformRole $role): array
    {
        $token = Str::random(64);

        $invitation = PlatformAdminInvitation::create([
            'email' => Str::lower($email),
            'role_id' => $role->id,
            'token_hash' => hash('sha256', $token),
            'invited_by' => $inviter->id,
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        app(AuditLogService::class)->record('administrator.invited', $invitation, [
            'new_values' => ['email' => $invitation->email, 'role_id' => $role->id],
        ]);

        return [$invitation, $token];
    }

    public function findPendingByToken(string $token): ?PlatformAdminInvitation
    {
        $invitation = PlatformAdminInvitation::query()
            ->with('role')
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $invitation) {
            return null;
        }

        if ($invitation->status === 'pending' && $invitation->expires_at->isPast()) {
            $invitation->forceFill(['status' => 'expired'])->save();
        }

        return $invitation->isPending() ? $invitation : null;
    }

    public function accept(PlatformAdminInvitation $invitation, array $data): CentralUser
    {
        return DB::transaction(function () use ($invitation, $data): CentralUser {
            abort_unless($invitation->isPending(), 422, 'This invitation can no longer be accepted.');

            $administrator = CentralUser::updateOrCreate(
                ['email' => $invitation->email],
                [
                    'name' => $data['name'],
                    'password' => Hash::make($data['password']),
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'invitation_accepted_at' => now(),
                ],
            );

            $administrator->syncRoles([$invitation->role]);
            $invitation->forceFill(['status' => 'accepted', 'accepted_at' => now()])->save();

            app(AuditLogService::class)->record('administrator.invitation_accepted', $invitation, [
                'entity_id' => $administrator->id,
                'new_values' => ['administrator_id' => $administrator->id],
            ]);

            return $administrator;
        });
    }

    public function revoke(PlatformAdminInvitation $invitation): void
    {
        abort_if($invitation->status !== 'pending', 422, 'Only pending invitations may be revoked.');

        $invitation->forceFill([
            'status' => 'revoked',
            'revoked_at' => now(),
        ])->save();

        app(AuditLogService::class)->record('administrator.invitation_revoked', $invitation, [
            'severity' => 'warning',
        ]);
    }
}
