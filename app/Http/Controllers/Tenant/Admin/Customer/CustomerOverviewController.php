<?php

namespace App\Http\Controllers\Tenant\Admin\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer\Company;
use App\Models\Customer\Contact;
use App\Models\Customer\CustomerImport;
use App\Models\Customer\CustomField;
use App\Models\Customer\Tag;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerOverviewController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user('tenant');
        $permissions = [
            'contacts' => $user->can('customers.view'),
            'companies' => $user->can('companies.view'),
            'imports' => $user->can('customers.import'),
            'tags' => $user->can('tags.manage'),
            'custom_fields' => $user->can('custom_fields.manage'),
        ];

        abort_unless(in_array(true, $permissions, true), 403);

        return Inertia::render('Tenant/Admin/Customers/Overview', [
            'counts' => [
                'contacts' => $permissions['contacts'] ? Contact::query()->count() : null,
                'companies' => $permissions['companies'] ? Company::query()->count() : null,
                'imports' => $permissions['imports'] ? CustomerImport::query()->count() : null,
                'tags' => $permissions['tags'] ? Tag::query()->count() : null,
                'custom_fields' => $permissions['custom_fields'] ? CustomField::query()->count() : null,
            ],
        ]);
    }
}
