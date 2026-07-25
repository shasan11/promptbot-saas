<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformOperation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Operations/Index', [
            'operations' => PlatformOperation::query()
                ->with('requester:id,name,email')
                ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')))
                ->when($request->string('type')->isNotEmpty(), fn ($query) => $query->where('type', $request->string('type')))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'filters' => $request->only(['status', 'type']),
        ]);
    }

    public function show(PlatformOperation $operation): Response
    {
        return Inertia::render('Admin/Operations/Show', [
            'operation' => $operation->load('requester:id,name,email'),
        ]);
    }
}
