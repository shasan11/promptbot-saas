<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CentralUser;
use App\Models\PlatformRole;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdministratorController extends Controller
{
    public function index(Request $request): Response
    {
        $administrators = CentralUser::query()
            ->with('roles:id,name')
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = '%'.$request->string('search')->toString().'%';
                $query->where(fn ($inner) => $inner->where('name', 'like', $search)->orWhere('email', 'like', $search));
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Administration/Administrators/Index', [
            'administrators' => $administrators,
            'filters' => $request->only('search'),
        ]);
    }

    public function show(CentralUser $administrator): Response
    {
        return Inertia::render('Admin/Administration/Administrators/Show', [
            'administrator' => $administrator->load('roles:id,name'),
            'roles' => PlatformRole::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
