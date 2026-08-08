<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\Tenant\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\TenantInvitation;
use App\Models\TenantRole;
use App\Models\User;
use App\Services\Tenant\TenantAuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class InvitationAcceptController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $invitation = $this->findValidInvitation($token);

        return Inertia::render('Tenant/InvitationAccept', [
            'invitation' => $invitation ? [
                'email' => $invitation->email,
                'name' => $invitation->name,
                'valid' => true,
            ] : ['valid' => false],
        ]);
    }

    public function accept(Request $request, string $token, TenantAuditLogService $auditLog): RedirectResponse
    {
        $invitation = $this->findValidInvitation($token);
        abort_unless($invitation, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);

        if (User::query()->where('email', $invitation->email)->exists()) {
            return back()->withErrors(['email' => 'An account with this email already exists. Please log in instead.']);
        }

        $user = DB::transaction(function () use ($invitation, $data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $invitation->email,
                'password' => Hash::make($data['password']),
                'job_title' => $invitation->job_title,
                'department_id' => $invitation->department_id,
                'locale' => $invitation->locale,
                'timezone' => $invitation->timezone,
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
                'password_changed_at' => now(),
            ]);

            if (! empty($invitation->role_ids)) {
                $user->syncRoles(TenantRole::query()->whereIn('id', $invitation->role_ids)->get());
            }

            if (! empty($invitation->team_ids)) {
                $user->teams()->sync($invitation->team_ids);
            }

            $invitation->forceFill([
                'status' => 'accepted',
                'accepted_at' => now(),
                'accepted_user_id' => $user->id,
                'token_hash' => hash('sha256', $invitation->token_hash.'-consumed-'.now()->timestamp),
            ])->save();

            return $user;
        });

        $auditLog->record('invitation.accepted', $user, "Invitation accepted by \"{$user->email}\"", $invitation);

        Auth::guard('tenant')->login($user);
        $request->session()->regenerate();

        return redirect()->route('tenant.admin.dashboard');
    }

    private function findValidInvitation(string $token): ?TenantInvitation
    {
        return TenantInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();
    }
}
