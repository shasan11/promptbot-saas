<?php

namespace App\Http\Controllers\Tenant\Admin\Connections;

use App\Http\Controllers\Controller;
use App\Models\Connections\SyncRun;
use App\Services\Connections\SyncRecoveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class SyncRunController extends Controller
{
    public function retry(Request $request, SyncRun $syncRun, SyncRecoveryService $recovery): RedirectResponse
    {
        abort_unless($request->user('tenant')?->can('connections.sync.run'), 403);

        try {
            $recovery->retry($syncRun, $request->user('tenant'));
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Sync retry queued.');
    }
}
