<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SupportTicketStoreRequest;
use App\Http\Requests\Admin\SupportTicketUpdateRequest;
use App\Models\CentralUser;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Services\Platform\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SupportTicketController extends Controller
{
    public function index(Request $request): Response
    {
        $tickets = SupportTicket::query()
            ->with(['tenant:id,company_name', 'assignee:id,name,email'])
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = '%'.$request->string('search')->toString().'%';
                $query->where(function ($inner) use ($search): void {
                    $inner->where('number', 'like', $search)
                        ->orWhere('subject', 'like', $search)
                        ->orWhere('requester_email', 'like', $search)
                        ->orWhereHas('tenant', fn ($tenant) => $tenant->where('company_name', 'like', $search));
                });
            })
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->string('priority')->isNotEmpty(), fn ($query) => $query->where('priority', $request->string('priority')))
            ->when($request->string('tenant_id')->isNotEmpty(), fn ($query) => $query->where('tenant_id', $request->string('tenant_id')))
            ->when($request->string('assigned_to')->isNotEmpty(), fn ($query) => $query->where('assigned_to', $request->integer('assigned_to')))
            ->orderByRaw("case priority when 'urgent' then 1 when 'high' then 2 when 'normal' then 3 else 4 end")
            ->latest('last_activity_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Tickets/Index', [
            'tickets' => $tickets,
            'tenants' => Tenant::query()->orderBy('company_name')->get(['id', 'company_name']),
            'administrators' => CentralUser::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']),
            'filters' => $request->only(['search', 'status', 'priority', 'tenant_id', 'assigned_to']),
            'stats' => [
                'open' => SupportTicket::query()->where('status', 'open')->count(),
                'pending' => SupportTicket::query()->where('status', 'pending')->count(),
                'urgent' => SupportTicket::query()->whereIn('status', ['open', 'pending'])->where('priority', 'urgent')->count(),
                'overdue' => SupportTicket::query()->whereIn('status', ['open', 'pending'])->whereNotNull('sla_due_at')->where('sla_due_at', '<', now())->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Tickets/Create', $this->formData());
    }

    public function store(SupportTicketStoreRequest $request, AuditLogService $auditLog): RedirectResponse
    {
        $ticket = DB::transaction(function () use ($request) {
            return SupportTicket::create([
                ...$request->validated(),
                'number' => $this->nextNumber(),
                'status' => 'open',
                'last_activity_at' => now(),
                'created_by' => $request->user('central')?->id,
            ]);
        });

        $auditLog->record('support_ticket.created', $ticket, [
            'tenant_id' => $ticket->tenant_id,
            'new_values' => $ticket->only(['number', 'subject', 'priority', 'category', 'assigned_to', 'sla_due_at']),
        ]);

        return redirect()->route('superadmin.tickets.show', $ticket)->with('status', 'Ticket created.');
    }

    public function show(SupportTicket $ticket): Response
    {
        return Inertia::render('Admin/Tickets/Show', [
            'ticket' => $ticket->load([
                'tenant:id,company_name,public_uuid',
                'assignee:id,name,email',
                'creator:id,name,email',
                'messages.centralUser:id,name,email',
            ]),
            'administrators' => CentralUser::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function update(
        SupportTicketUpdateRequest $request,
        SupportTicket $ticket,
        AuditLogService $auditLog
    ): RedirectResponse {
        $data = $request->validated();
        $status = $data['status'] ?? $ticket->status;

        if ($status === 'resolved' && $ticket->status !== 'resolved') {
            $data['resolved_at'] = now();
            $data['closed_at'] = null;
        } elseif ($status === 'closed' && $ticket->status !== 'closed') {
            $data['closed_at'] = now();
            $data['resolved_at'] ??= $ticket->resolved_at ?? now();
        } elseif (in_array($status, ['open', 'pending'], true)) {
            $data['resolved_at'] = null;
            $data['closed_at'] = null;
        }

        $data['last_activity_at'] = now();
        $oldValues = $ticket->only(array_keys($data));
        $ticket->update($data);

        $auditLog->record('support_ticket.updated', $ticket, [
            'tenant_id' => $ticket->tenant_id,
            'old_values' => $oldValues,
            'new_values' => $data,
        ]);

        return back()->with('status', 'Ticket updated.');
    }

    public function addMessage(Request $request, SupportTicket $ticket, AuditLogService $auditLog): RedirectResponse
    {
        abort_unless($request->user('central')?->can('support.manage'), 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'is_internal' => ['sometimes', 'boolean'],
        ]);

        $message = $ticket->messages()->create([
            'central_user_id' => $request->user('central')?->id,
            'body' => $validated['body'],
            'is_internal' => (bool) ($validated['is_internal'] ?? false),
        ]);

        $ticket->update(['last_activity_at' => now()]);

        $auditLog->record('support_ticket.message_added', $ticket, [
            'tenant_id' => $ticket->tenant_id,
            'new_values' => ['message_id' => $message->id, 'is_internal' => $message->is_internal],
        ]);

        return back()->with('status', $message->is_internal ? 'Internal note added.' : 'Reply added.');
    }

    private function formData(): array
    {
        return [
            'tenants' => Tenant::query()->orderBy('company_name')->get(['id', 'company_name']),
            'administrators' => CentralUser::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']),
        ];
    }

    private function nextNumber(): string
    {
        $count = SupportTicket::query()->lockForUpdate()->count();

        return 'TKT-'.str_pad((string) ($count + 1), 6, '0', STR_PAD_LEFT);
    }
}
