<?php

namespace App\Http\Controllers\Portal;

use App\Enums\PortalUserStatus;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveActiveCustomerAccount;
use App\Models\CustomerAccountInvitation;
use App\Models\PortalUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    public function show(CustomerAccountInvitation $invitation, string $token): Response
    {
        $this->validateToken($invitation, $token);
        return Inertia::render('Portal/Auth/AcceptInvitation', [
            'invitation' => $invitation->load('account:id,public_uuid,name'), 'token' => $token,
            'existingUser' => PortalUser::where('email', $invitation->email)->exists(),
        ]);
    }

    public function store(Request $request, CustomerAccountInvitation $invitation, string $token): RedirectResponse
    {
        $this->validateToken($invitation, $token);
        $existing = PortalUser::where('email', $invitation->email)->first();
        $data = $request->validate($existing ? [] : [
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($invitation, $existing, $data): PortalUser {
            $user = $existing ?: PortalUser::create([
                'name' => $data['name'], 'email' => $invitation->email, 'password' => $data['password'],
                'status' => PortalUserStatus::Active,
            ]);
            $invitation->account->users()->syncWithoutDetaching([$user->getKey() => [
                'role' => $invitation->role,
                'can_manage_services' => $invitation->can_manage_services,
                'can_manage_billing' => $invitation->can_manage_billing,
                'can_manage_members' => $invitation->can_manage_members,
                'can_manage_support' => $invitation->can_manage_support,
                'service_access' => $invitation->service_access,
                'invited_by' => $invitation->invited_by,
                'joined_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]]);
            if ($invitation->service_access === 'selected') {
                DB::table('customer_account_user_tenants')->insertOrIgnore(collect($invitation->tenant_ids ?? [])->map(fn ($tenantId) => [
                    'customer_account_id' => $invitation->customer_account_id, 'portal_user_id' => $user->getKey(), 'tenant_id' => $tenantId,
                    'created_at' => now(), 'updated_at' => now(),
                ])->all());
            }
            $invitation->update(['accepted_at' => now()]);
            return $user;
        });

        Auth::guard('portal')->login($user);
        $request->session()->put(ResolveActiveCustomerAccount::SESSION_KEY, $invitation->customer_account_id);
        return redirect()->route('portal.dashboard')->with('status', "You joined {$invitation->account->name}.");
    }

    private function validateToken(CustomerAccountInvitation $invitation, string $token): void
    {
        abort_unless(! $invitation->accepted_at && $invitation->expires_at->isFuture() && hash_equals($invitation->token_hash, hash('sha256', $token)), 404);
    }
}
