<?php

namespace App\Http\Controllers\Portal;

use App\Models\PortalUser;
use App\Models\CustomerAccountInvitation;
use App\Models\CustomerAccountActivity;
use App\Services\Platform\AccountInvitationService;
use App\Services\Platform\AuditLogService;
use App\Services\Platform\PlatformSettingsService;
use App\Services\Platform\AccountLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends PortalController
{
    public function index(Request $request, PlatformSettingsService $settings): Response
    {
        $account = $this->account($request);
        $this->authorize('manageMembers', $account);
        return Inertia::render('Portal/Members/Index', [
            'members' => $account->users()->orderBy('name')->get(),
            'workspaces' => $account->tenants()->orderBy('company_name')->get(['id', 'company_name']),
            'accessGrants' => DB::table('customer_account_user_tenants')->where('customer_account_id', $account->getKey())
                ->get()->groupBy('portal_user_id')->map->pluck('tenant_id'),
            'invitations' => CustomerAccountInvitation::where('customer_account_id', $account->getKey())
                ->whereNull('accepted_at')->where('expires_at', '>', now())->latest()->get(),
            'invitationsEnabled' => filter_var($settings->get('customer_portal', 'allow_member_invitations', true), FILTER_VALIDATE_BOOL),
        ]);
    }

    public function store(Request $request, AccountInvitationService $invitations, PlatformSettingsService $settings, AccountLimitService $limits): RedirectResponse
    {
        $account = $this->account($request);
        $this->authorize('manageMembers', $account);
        abort_unless(filter_var($settings->get('customer_portal', 'allow_member_invitations', true), FILTER_VALIDATE_BOOL), 403);
        abort_if($limits->reached($account, 'members', $account->users()->count()), 422, 'This account has reached its member limit.');
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(['admin', 'billing', 'member', 'viewer'])],
            'can_manage_services' => ['boolean'], 'can_manage_billing' => ['boolean'],
            'can_manage_members' => ['boolean'], 'can_manage_support' => ['boolean'],
            'service_access' => ['required', 'in:all,selected'],
            'tenant_ids' => ['array', 'required_if:service_access,selected'],
            'tenant_ids.*' => [Rule::exists('tenants', 'id')->where(fn ($query) => $query->where('customer_account_id', $account->getKey()))],
        ]);
        if ($data['role'] === 'billing') $data['can_manage_billing'] = true;
        abort_if($account->users()->where('portal_users.email', strtolower($data['email']))->exists(), 422, 'This user is already a member.');
        $invitations->invite($account, $request->user('portal'), $data);
        return back()->with('status', 'Invitation sent.');
    }

    public function update(Request $request, PortalUser $member, AuditLogService $audit): RedirectResponse
    {
        $account = $this->account($request);
        $this->authorize('manageMembers', $account);
        abort_if($account->primary_owner_user_id === $member->getKey(), 422, 'Transfer ownership before changing the owner role.');
        abort_unless($member->belongsToAccount($account), 404);
        $data = $request->validate([
            'role' => ['required', Rule::in(['admin', 'billing', 'member', 'viewer'])],
            'can_manage_services' => ['boolean'], 'can_manage_billing' => ['boolean'],
            'can_manage_members' => ['boolean'], 'can_manage_support' => ['boolean'],
            'service_access' => ['required', 'in:all,selected'],
            'tenant_ids' => ['array', 'required_if:service_access,selected'],
            'tenant_ids.*' => [Rule::exists('tenants', 'id')->where(fn ($query) => $query->where('customer_account_id', $account->getKey()))],
        ]);
        if ($data['role'] === 'billing') $data['can_manage_billing'] = true;
        $old = $account->users()->where('portal_users.id', $member->getKey())->firstOrFail()->pivot->toArray();
        DB::transaction(function () use ($account, $member, $data, $request): void {
            $account->users()->updateExistingPivot($member->getKey(), collect($data)->except('tenant_ids')->all());
            DB::table('customer_account_user_tenants')->where('customer_account_id', $account->getKey())->where('portal_user_id', $member->getKey())->delete();
            if ($data['service_access'] === 'selected') {
                DB::table('customer_account_user_tenants')->insert(collect($data['tenant_ids'] ?? [])->unique()->map(fn ($tenantId) => [
                    'customer_account_id' => $account->getKey(), 'portal_user_id' => $member->getKey(), 'tenant_id' => $tenantId,
                    'created_at' => now(), 'updated_at' => now(),
                ])->all());
            }
            CustomerAccountActivity::create([
                'customer_account_id' => $account->getKey(), 'actor_type' => PortalUser::class,
                'actor_id' => (string) $request->user('portal')->getKey(), 'event' => 'member.access_updated',
                'subject_type' => PortalUser::class, 'subject_id' => (string) $member->getKey(),
                'description' => "Access for {$member->email} was updated.",
            ]);
        });
        $audit->record('portal.member.updated', $member, ['old_values' => $old, 'new_values' => collect($data)->except('tenant_ids')->all()]);
        return back()->with('status', 'Member access updated.');
    }

    public function destroy(Request $request, PortalUser $member, AuditLogService $audit): RedirectResponse
    {
        $account = $this->account($request);
        $this->authorize('manageMembers', $account);
        abort_if($account->primary_owner_user_id === $member->getKey(), 422, 'The account owner cannot be removed.');
        abort_unless($member->belongsToAccount($account), 404);
        DB::transaction(function () use ($account, $member, $request): void {
            $account->users()->detach($member->getKey());
            CustomerAccountActivity::create([
                'customer_account_id' => $account->getKey(), 'actor_type' => PortalUser::class,
                'actor_id' => (string) $request->user('portal')->getKey(), 'event' => 'member.removed',
                'subject_type' => PortalUser::class, 'subject_id' => (string) $member->getKey(),
                'description' => "{$member->email} was removed from the account.",
            ]);
        });
        $audit->record('portal.member.removed', $member, ['new_values' => ['customer_account_id' => $account->getKey()]]);
        return back()->with('status', 'Member removed.');
    }

    public function transfer(Request $request, AuditLogService $audit): RedirectResponse
    {
        $account = $this->account($request);
        $this->authorize('transferOwnership', $account);
        $data = $request->validate(['portal_user_id' => ['required', 'integer'], 'current_password' => ['required', 'string']]);
        abort_unless(Hash::check($data['current_password'], $request->user('portal')->password), 422, 'Current password is incorrect.');
        $newOwner = $account->users()->where('portal_users.id', $data['portal_user_id'])->firstOrFail();
        abort_unless($newOwner->hasVerifiedEmail(), 422, 'The new owner must verify their email first.');

        DB::transaction(function () use ($account, $newOwner, $request): void {
            $account->users()->updateExistingPivot($request->user('portal')->getKey(), ['role' => 'admin']);
            $account->users()->updateExistingPivot($newOwner->getKey(), [
                'role' => 'owner', 'can_manage_services' => true, 'can_manage_billing' => true,
                'can_manage_members' => true, 'can_manage_support' => true,
            ]);
            $account->update(['primary_owner_user_id' => $newOwner->getKey()]);
            CustomerAccountActivity::create([
                'customer_account_id' => $account->getKey(), 'actor_type' => PortalUser::class,
                'actor_id' => (string) $request->user('portal')->getKey(), 'event' => 'ownership.transferred',
                'subject_type' => PortalUser::class, 'subject_id' => (string) $newOwner->getKey(),
                'description' => "Account ownership was transferred to {$newOwner->email}.",
            ]);
        });
        $audit->record('portal.account.ownership_transferred', $account, [
            'old_values' => ['primary_owner_user_id' => $request->user('portal')->getKey()],
            'new_values' => ['primary_owner_user_id' => $newOwner->getKey()], 'severity' => 'warning',
        ]);
        return back()->with('status', 'Account ownership transferred.');
    }
}
