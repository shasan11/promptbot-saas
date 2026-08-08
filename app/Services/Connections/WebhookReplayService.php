<?php

namespace App\Services\Connections;

use App\Models\Connections\WebhookDeliveryAttempt;
use App\Models\Connections\WebhookEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WebhookReplayService
{
    public function __construct(
        private readonly ConnectionAuditService $audit,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function replay(WebhookEvent $event, User $actor): WebhookDeliveryAttempt
    {
        if (! in_array($event->status, ['failed', 'processing_failed', 'replay_failed'], true)) {
            throw new InvalidArgumentException('Only failed webhook events can be replayed.');
        }

        $event->loadMissing(['endpoint', 'connection']);

        if (! $event->endpoint || $event->endpoint->status !== 'active') {
            throw new InvalidArgumentException('The webhook endpoint is not active.');
        }

        return DB::transaction(function () use ($event, $actor): WebhookDeliveryAttempt {
            $providerEventId = $event->provider_event_id ?: $event->payload_hash ?: (string) $event->uuid;
            $idempotency = $this->idempotency->start(
                'webhook.replay',
                $event->id.'|'.$providerEventId,
                $event->connection,
                4320,
            );

            if ($idempotency->status === 'completed') {
                throw new InvalidArgumentException('This webhook replay was already completed.');
            }

            $attempt = WebhookDeliveryAttempt::create([
                'tenant_id' => tenant('id'),
                'webhook_event_id' => $event->id,
                'attempt' => $event->attempts()->count() + 1,
                'status' => 'replay_queued',
                'response_status' => null,
                'latency_ms' => null,
                'attempted_at' => now(),
            ]);

            $event->forceFill([
                'status' => 'replay_queued',
                'replayed_at' => now(),
                'replayed_by' => $actor->id,
                'error_message' => null,
            ])->save();

            $idempotency->forceFill([
                'status' => 'completed',
                'response' => ['webhook_event_id' => $event->id, 'attempt_id' => $attempt->id],
            ])->save();

            $this->audit->record('webhook.replayed', $event->connection, $actor, message: 'Webhook event replay queued.', context: [
                'webhook_event_id' => $event->id,
                'webhook_endpoint_id' => $event->webhook_endpoint_id,
                'provider_event_id' => $event->provider_event_id,
                'payload_hash' => $event->payload_hash,
                'attempt' => $attempt->attempt,
            ]);

            return $attempt;
        });
    }
}
