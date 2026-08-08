<?php

namespace App\Http\Controllers\Tenant\Connections;

use App\Http\Controllers\Controller;
use App\Models\Connections\WebhookDeliveryAttempt;
use App\Models\Connections\WebhookEndpoint;
use App\Models\Connections\WebhookEvent;
use App\Services\Connections\IdempotencyService;
use App\Services\Connections\SecretRedactor;
use App\Services\Connections\WebhookSecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class InboundWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        string $endpointPath,
        WebhookSecurityService $security,
        SecretRedactor $redactor,
        IdempotencyService $idempotency,
    ): JsonResponse {
        $started = microtime(true);
        $endpoint = WebhookEndpoint::query()
            ->where('endpoint_path', $endpointPath)
            ->where('status', 'active')
            ->firstOrFail();

        try {
            $validated = $security->validate($request, $endpoint);
            $providerEventId = (string) ($request->header('X-Event-ID') ?? data_get($validated['payload'], 'event_id') ?? $validated['payload_hash']);
            $idempotency->start('webhook.receive', $endpoint->id.'|'.$providerEventId, $endpoint->connection, 4320);

            $event = WebhookEvent::firstOrCreate(
                ['webhook_endpoint_id' => $endpoint->id, 'provider_event_id' => $providerEventId],
                [
                    'tenant_id' => tenant('id'),
                    'connection_id' => $endpoint->connection_id,
                    'event_type' => (string) ($request->header('X-Event-Type') ?? data_get($validated['payload'], 'type') ?? 'webhook.received'),
                    'status' => 'received',
                    'http_method' => $request->method(),
                    'headers' => $redactor->redact($request->headers->all()),
                    'payload' => $redactor->redact($validated['payload']),
                    'payload_hash' => $validated['payload_hash'],
                    'payload_size' => $validated['payload_size'],
                    'signature' => $validated['signature'],
                    'received_at' => now(),
                    'response_status' => 202,
                    'latency_ms' => max(1, (int) round((microtime(true) - $started) * 1000)),
                ]
            );

            $endpoint->forceFill(['last_received_at' => now()])->save();
            WebhookDeliveryAttempt::create([
                'tenant_id' => tenant('id'),
                'webhook_event_id' => $event->id,
                'attempt' => $event->attempts()->count() + 1,
                'status' => $event->wasRecentlyCreated ? 'accepted' : 'duplicate',
                'response_status' => 202,
                'latency_ms' => max(1, (int) round((microtime(true) - $started) * 1000)),
                'attempted_at' => now(),
            ]);

            return response()->json(['status' => $event->wasRecentlyCreated ? 'accepted' : 'duplicate'], 202);
        } catch (Throwable $exception) {
            $event = WebhookEvent::create([
                'tenant_id' => tenant('id'),
                'webhook_endpoint_id' => $endpoint->id,
                'connection_id' => $endpoint->connection_id,
                'provider_event_id' => $request->header('X-Event-ID'),
                'event_type' => $request->header('X-Event-Type'),
                'status' => 'failed',
                'http_method' => $request->method(),
                'headers' => $redactor->redact($request->headers->all()),
                'payload_hash' => hash('sha256', $request->getContent()),
                'payload_size' => strlen($request->getContent()),
                'received_at' => now(),
                'response_status' => 400,
                'latency_ms' => max(1, (int) round((microtime(true) - $started) * 1000)),
                'error_message' => $exception->getMessage(),
            ]);

            WebhookDeliveryAttempt::create([
                'tenant_id' => tenant('id'),
                'webhook_event_id' => $event->id,
                'attempt' => 1,
                'status' => 'failed',
                'response_status' => 400,
                'latency_ms' => max(1, (int) round((microtime(true) - $started) * 1000)),
                'error_message' => $exception->getMessage(),
                'attempted_at' => now(),
            ]);

            return response()->json(['message' => 'Webhook rejected.'], 400);
        }
    }
}
