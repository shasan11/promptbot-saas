<?php

namespace App\Http\Controllers\Tenant\Admin\Connections;

use App\Http\Controllers\Controller;
use App\Models\Connections\WebhookEvent;
use App\Services\Connections\WebhookReplayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class WebhookEventController extends Controller
{
    public function replay(Request $request, WebhookEvent $event, WebhookReplayService $replay): RedirectResponse
    {
        abort_unless($request->user('tenant')?->can('connections.webhooks.manage'), 403);

        try {
            $replay->replay($event, $request->user('tenant'));
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Webhook replay queued.');
    }
}
