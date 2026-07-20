<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Tenant/Admin/Users/Index', [
            'users' => User::query()
                ->with('roles:id,name,label')
                ->latest()
                ->paginate(15),
            'roles' => Role::query()
                ->orderBy('label')
                ->get(['id', 'name', 'label']),
        ]);
    }
}
