<?php

namespace App\Http\Controllers;

use App\Services\Platform\PaymentWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, PaymentWebhookService $webhooks): JsonResponse
    {
        $provider = strtolower($provider);
        abort_unless(in_array($provider, ['stripe', 'paypal', 'khalti', 'esewa'], true), 404);
        $raw = $request->getContent();
        abort_unless($webhooks->verify($provider, $raw, (string) $request->header('X-PromptBot-Signature')), 401);
        $event = $webhooks->handle($provider, $request->json()->all(), $raw);

        return response()->json(['accepted' => true, 'event_id' => $event->provider_event_id]);
    }
}
