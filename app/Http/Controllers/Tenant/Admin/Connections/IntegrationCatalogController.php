<?php

namespace App\Http\Controllers\Tenant\Admin\Connections;

use App\Http\Controllers\Controller;
use App\Models\Connections\ConnectionIntegration;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationCatalogController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user('tenant')?->can('connections.catalog.view'), 403);

        $query = ConnectionIntegration::query()->withCount('connections')->orderBy('category')->orderBy('name');

        if ($search = $request->string('search')->toString()) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('provider', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%"));
        }

        if ($category = $request->string('category')->toString()) {
            $query->where('category', $category);
        }

        return Inertia::render('Tenant/Admin/Connections/Apps/Index', [
            'integrations' => $query->paginate(12)->withQueryString(),
            'categories' => ConnectionIntegration::query()->distinct()->orderBy('category')->pluck('category')->values(),
            'filters' => $request->only(['search', 'category']),
        ]);
    }

    public function show(Request $request, ConnectionIntegration $integration): Response
    {
        abort_unless($request->user('tenant')?->can('connections.catalog.view'), 403);

        return Inertia::render('Tenant/Admin/Connections/Apps/Show', [
            'integration' => $integration->load(['connections' => fn ($query) => $query->latest()->limit(8)]),
        ]);
    }
}
