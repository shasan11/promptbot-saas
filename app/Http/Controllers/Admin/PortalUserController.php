<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PortalUser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalUserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PortalUser::class);
        $users = PortalUser::query()->withCount('accounts')->with('accounts:id,public_uuid,name')
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = '%'.$request->string('search').'%';
                $query->where(fn ($inner) => $inner->where('name', 'like', $search)->orWhere('email', 'like', $search));
            })->latest()->paginate(20)->withQueryString();

        return Inertia::render('Admin/Customers/Users/Index', ['users' => $users, 'filters' => $request->only(['search', 'status'])]);
    }
}
