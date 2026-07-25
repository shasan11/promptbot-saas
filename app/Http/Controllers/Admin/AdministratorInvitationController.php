<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformAdminInvitation;
use App\Models\PlatformRole;
use App\Services\Platform\AdministratorInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdministratorInvitationController extends Controller
{
    public function __construct(private readonly AdministratorInvitationService $invitations) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Administration/Invitations/Index', [
            'invitations' => PlatformAdminInvitation::query()
                ->with(['role:id,name', 'inviter:id,name,email'])
                ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'filters' => $request->only('status'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Administration/Invitations/Create', [
            'roles' => PlatformRole::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255', 'unique:central_users,email'],
            'role_id' => ['required', 'uuid', 'exists:platform_roles,id'],
        ]);

        $role = PlatformRole::findOrFail($validated['role_id']);
        [, $token] = $this->invitations->invite($request->user('central'), $validated['email'], $role);

        return redirect()
            ->route('superadmin.administrators.invitations.index')
            ->with('status', 'Invitation created. Send this one-time link securely: '.route('superadmin.invitations.accept', $token));
    }

    public function revoke(PlatformAdminInvitation $invitation): RedirectResponse
    {
        $this->invitations->revoke($invitation);

        return back()->with('status', 'Invitation revoked.');
    }
}
