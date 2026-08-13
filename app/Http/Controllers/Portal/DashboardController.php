<?php

namespace App\Http\Controllers\Portal;

use App\Services\Platform\CustomerPortalService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends PortalController
{
    public function __invoke(Request $request, CustomerPortalService $portal): Response
    {
        $account = $this->account($request);
        $this->authorize('view', $account);

        return Inertia::render('Portal/Dashboard', $portal->overview($account, $request->user('portal')));
    }
}
