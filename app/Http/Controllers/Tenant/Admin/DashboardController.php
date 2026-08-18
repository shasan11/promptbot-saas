<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Models\Channel\Channel;
use App\Models\Customer\Contact;
use App\Models\Inbox\Conversation;
use App\Models\Setting;
use App\Models\Task\Task;
use App\Models\TenantPermission;
use App\Models\TenantRole;
use App\Models\Ticket\Ticket;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $startDate = now()->subDays(6)->startOfDay();
        $conversationVolume = Conversation::query()
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as activity_date, COUNT(*) as aggregate')
            ->groupBy('activity_date')
            ->pluck('aggregate', 'activity_date');
        $ticketVolume = Ticket::query()
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as activity_date, COUNT(*) as aggregate')
            ->groupBy('activity_date')
            ->pluck('aggregate', 'activity_date');

        return Inertia::render('Tenant/Admin/Dashboard', [
            // Deliberately not passing 'tenant' here: HandleInertiaRequests
            // already shares a richer 'tenant' prop (id, companyName, logoUrl,
            // faviconUrl, colors) on every request. A page-local prop with the
            // same key would shadow it for this page only, which previously
            // broke the header logo/branding specifically on the dashboard —
            // the first page a user lands on after login.
            'stats' => [
                'users' => User::query()->count(),
                'roles' => TenantRole::query()->count(),
                'permissions' => TenantPermission::query()->count(),
                'settings' => Setting::query()->count(),
                'openConversations' => Conversation::query()->whereIn('status', ['open', 'pending', 'waiting_on_customer'])->count(),
                'unreadConversations' => Conversation::query()->where('unread_count', '>', 0)->count(),
                'unresolvedTickets' => Ticket::query()->whereHas('status', fn ($query) => $query->whereNotIn('category', ['resolved', 'closed']))->count(),
                'openTasks' => Task::query()->whereNotIn('status', ['completed', 'cancelled'])->count(),
                'overdueTasks' => Task::query()->whereNotIn('status', ['completed', 'cancelled'])->where('due_at', '<', now())->count(),
                'contacts' => Contact::query()->count(),
                'activeChannels' => Channel::query()->where('status', 'active')->count(),
            ],
            'activity' => collect(range(0, 6))->map(function (int $offset) use ($startDate, $conversationVolume, $ticketVolume): array {
                $date = $startDate->copy()->addDays($offset);

                return [
                    'date' => $date->toDateString(),
                    'label' => $date->format('D'),
                    'conversations' => (int) ($conversationVolume[$date->toDateString()] ?? 0),
                    'tickets' => (int) ($ticketVolume[$date->toDateString()] ?? 0),
                ];
            }),
            'recentConversations' => Conversation::query()
                ->with(['contact:id,public_uuid,display_name,email', 'channel:id,name,type', 'assignee:id,name'])
                ->latest('last_message_at')
                ->limit(6)
                ->get(['id', 'public_uuid', 'contact_id', 'channel_id', 'assignee_id', 'subject', 'status', 'priority', 'unread_count', 'last_message_at']),
            'priorityTasks' => Task::query()
                ->with('assignee:id,name')
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->orderByRaw('due_at IS NULL')
                ->orderBy('due_at')
                ->limit(5)
                ->get(['id', 'public_uuid', 'title', 'status', 'priority', 'assigned_to', 'due_at']),
            'recentUsers' => User::query()
                ->with('roles:id,name,label')
                ->latest()
                ->limit(6)
                ->get(['id', 'name', 'email', 'created_at']),
        ]);
    }
}
