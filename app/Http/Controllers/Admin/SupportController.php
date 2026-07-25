<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SupportController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Support/Index', [
            'tickets' => DB::table('support_tickets')
                ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')))
                ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                    $query->where('subject', 'like', '%'.$request->string('search')->toString().'%');
                })
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    public function show(string $ticket): Response
    {
        return Inertia::render('Admin/Support/Show', [
            'ticket' => DB::table('support_tickets')->where('id', $ticket)->firstOrFail(),
            'messages' => DB::table('support_messages')->where('support_ticket_id', $ticket)->orderBy('created_at')->get(),
        ]);
    }
}
