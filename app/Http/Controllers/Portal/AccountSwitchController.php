<?php

namespace App\Http\Controllers\Portal;

use App\Http\Middleware\ResolveActiveCustomerAccount;
use App\Models\CustomerAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AccountSwitchController extends PortalController
{
    public function __invoke(Request $request, CustomerAccount $account): RedirectResponse
    {
        $this->authorize('view', $account);
        $request->session()->put(ResolveActiveCustomerAccount::SESSION_KEY, $account->getKey());

        return back()->with('status', "Switched to {$account->name}.");
    }
}
