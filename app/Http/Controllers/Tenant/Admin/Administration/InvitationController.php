<?php

namespace App\Http\Controllers\Tenant\Admin\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Administration\InvitationStoreRequest;
use App\Jobs\Tenant\SendTenantInvitationJob;
use App\Models\Department;
use App\Models\Team;
use App\Models\TenantInvitation;
use App\Models\TenantRole;
use App\Models\User;
use App\Services\Tenant\TenantAuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    public function __construct(private readonly TenantAuditLogService $auditLog) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', TenantInvitation::class);

        $this->expireStalePending();

        return Inertia::render('Tenant/Admin/Administration/Invitations/Index', [
            'invitations' => TenantInvitation::query()
                ->with(['inviter:id,name', 'department:id,name'])
                ->orderByDesc('created_at')
                ->paginate(20),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', TenantInvitation::class);

        return Inertia::render('Tenant/Admin/Administration/Invitations/Create', [
            'roles' => TenantRole::query()->orderBy('name')->get(['id', 'name', 'label']),
            'teams' => Team::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'departments' => Department::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(InvitationStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if (User::query()->where('email', $data['email'])->exists()) {
            return back()->withErrors(['email' => 'A user with this email already exists in this workspace.'])->withInput();
        }

        if (TenantInvitation::query()->where('email', $data['email'])->where('status', 'pending')->exists()) {
            return back()->withErrors(['email' => 'There is already a pending invitation for this email address.'])->withInput();
        }

        [$plainToken, $tokenHash] = TenantInvitation::generateToken();

        $invitation = TenantInvitation::create([
            'email' => $data['email'],
            'name' => $data['name'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'token_hash' => $tokenHash,
            'role_ids' => $data['role_ids'] ?? [],
            'team_ids' => $data['team_ids'] ?? [],
            'department_id' => $data['department_id'] ?? null,
            'message' => $data['message'] ?? null,
            'invited_by' => $request->user('tenant')?->id,
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        SendTenantInvitationJob::dispatch($invitation->id, $plainToken);

        $this->auditLog->record('invitation.created', $request->user('tenant'), "Invited \"{$invitation->email}\"", $invitation, newValues: ['email' => $invitation->email]);

        return redirect()->route('tenant.admin.administration.invitations.index')->with('status', 'Invitation sent.');
    }

    public function resend(Request $request, TenantInvitation $invitation): RedirectResponse
    {
        Gate::authorize('resend', $invitation);

        abort_if($invitation->status !== 'pending', 422, 'Only pending invitations can be resent.');

        $key = "invitation-resend:{$invitation->id}";
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->with('error', 'This invitation was resent too many times recently. Try again later.');
        }
        RateLimiter::hit($key, 3600);

        [$plainToken, $tokenHash] = TenantInvitation::generateToken();
        $invitation->forceFill([
            'token_hash' => $tokenHash,
            'expires_at' => now()->addDays(7),
            'send_count' => $invitation->send_count + 1,
        ])->save();

        SendTenantInvitationJob::dispatch($invitation->id, $plainToken);

        $this->auditLog->record('invitation.resent', $request->user('tenant'), "Resent invitation to \"{$invitation->email}\"", $invitation);

        return back()->with('status', 'Invitation resent.');
    }

    public function revoke(Request $request, TenantInvitation $invitation): RedirectResponse
    {
        Gate::authorize('revoke', $invitation);

        abort_if($invitation->status !== 'pending', 422, 'Only pending invitations can be revoked.');

        $invitation->forceFill(['status' => 'revoked', 'revoked_at' => now(), 'revoked_by' => $request->user('tenant')?->id])->save();

        $this->auditLog->record('invitation.revoked', $request->user('tenant'), "Revoked invitation to \"{$invitation->email}\"", $invitation);

        return back()->with('status', 'Invitation revoked.');
    }

    public function destroy(TenantInvitation $invitation): RedirectResponse
    {
        Gate::authorize('revoke', $invitation);

        $invitation->delete();

        return back()->with('status', 'Invitation removed.');
    }

    private function expireStalePending(): void
    {
        TenantInvitation::query()->where('status', 'pending')->where('expires_at', '<', now())->update(['status' => 'expired']);
    }
}
