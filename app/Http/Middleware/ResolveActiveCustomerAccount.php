<?php

namespace App\Http\Middleware;

use App\Models\CustomerAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveActiveCustomerAccount
{
    public const SESSION_KEY = 'portal.active_customer_account_id';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('portal');
        $requestedId = $request->session()->get(self::SESSION_KEY);

        $account = $user->accounts()
            ->when($requestedId, fn ($query) => $query->where('customer_accounts.id', $requestedId))
            ->first();

        $account ??= $user->accounts()->orderBy('customer_accounts.name')->first();
        abort_unless($account instanceof CustomerAccount, 403, 'No customer account is available.');

        $request->session()->put(self::SESSION_KEY, $account->getKey());
        $request->attributes->set('customerAccount', $account);

        return $next($request);
    }
}
