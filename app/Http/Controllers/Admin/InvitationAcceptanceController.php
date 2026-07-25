<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Platform\AdministratorInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class InvitationAcceptanceController extends Controller
{
    public function __construct(private readonly AdministratorInvitationService $invitations) {}

    public function show(string $token): Response
    {
        $invitation = $this->invitations->findPendingByToken($token);
        abort_unless($invitation, 404);

        return Inertia::render('Admin/Administration/Invitations/Accept', [
            'token' => $token,
            'email' => $invitation->email,
            'role' => $invitation->role?->name,
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->invitations->findPendingByToken($token);
        abort_unless($invitation, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $administrator = $this->invitations->accept($invitation, $validated);
        Auth::guard('central')->login($administrator);
        $request->session()->regenerate();

        return redirect()->route('superadmin.security.two-factor')->with('status', 'Invitation accepted. Set up two-factor authentication next.');
    }
}
