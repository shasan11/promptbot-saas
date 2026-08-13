<?php

namespace App\Http\Controllers\Portal;

use App\Models\PortalNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\PortalNotificationPreference;

class NotificationController extends PortalController
{
    private const EVENTS = ['invoice_issued', 'payment_successful', 'payment_failed', 'subscription_renewal', 'trial_ending', 'workspace_provisioned', 'workspace_suspended', 'support_ticket_reply'];

    public function index(Request $request): Response
    {
        $account = $this->account($request);
        $this->authorize('view', $account);
        $preferences = PortalNotificationPreference::firstOrCreate(['portal_user_id' => $request->user('portal')->id, 'customer_account_id' => $account->id]);
        return Inertia::render('Portal/Account/Notifications', [
            'notifications' => PortalNotification::where('customer_account_id', $account->id)->where(fn ($query) => $query->whereNull('portal_user_id')->orWhere('portal_user_id', $request->user('portal')->id))->latest()->paginate(30),
            'preferences' => $preferences,
            'eventChannels' => collect(self::EVENTS)->mapWithKeys(fn (string $event) => [$event => [
                'email' => $preferences->channelEnabled($event, 'email'),
                'in_app' => $preferences->channelEnabled($event, 'in_app'),
            ]]),
        ]);
    }

    public function read(Request $request, PortalNotification $notification): RedirectResponse
    {
        $account = $this->account($request);
        abort_unless($notification->customer_account_id === $account->id && (! $notification->portal_user_id || $notification->portal_user_id === $request->user('portal')->id), 404);
        $notification->update(['read_at' => now()]);
        $url = $notification->url;
        return $url && str_starts_with($url, '/account') ? redirect()->to($url) : back();
    }

    public function preferences(Request $request): RedirectResponse
    {
        $account = $this->account($request);
        $this->authorize('view', $account);
        $data = $request->validate([
            'event_channels' => ['required', 'array:'.implode(',', self::EVENTS)],
            'event_channels.*' => ['required', 'array:email,in_app'],
            'event_channels.*.email' => ['required', 'boolean'],
            'event_channels.*.in_app' => ['required', 'boolean'],
        ]);
        PortalNotificationPreference::updateOrCreate(
            ['portal_user_id' => $request->user('portal')->id, 'customer_account_id' => $account->id], $data
        );
        return back()->with('status', 'Notification preferences updated.');
    }
}
