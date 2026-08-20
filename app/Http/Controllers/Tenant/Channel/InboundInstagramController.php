<?php

namespace App\Http\Controllers\Tenant\Channel;

use App\Http\Controllers\Controller;
use App\Models\Channel\Channel;
use App\Models\Customer\Contact;
use App\Services\Channels\Support\MetaWebhookVerifier;
use App\Services\Inbox\ConversationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InboundInstagramController extends Controller
{
    public function __invoke(Request $request, Channel $channel, ConversationService $service, MetaWebhookVerifier $verifier): Response
    {
        abort_unless($channel->type === 'instagram' && $channel->status === 'active', 404);

        $secret = $channel->credential?->encrypted_payload ?? [];

        if ($request->isMethod('get')) {
            $verified = $request->query('hub_mode') === 'subscribe'
                && hash_equals((string) ($secret['verify_token'] ?? ''), (string) $request->query('hub_verify_token'));

            abort_unless($verified, 403, 'Verify token mismatch.');

            return response((string) $request->query('hub_challenge'), 200);
        }

        abort_unless(
            $verifier->verify($request->getContent(), $request->header('X-Hub-Signature-256'), (string) ($secret['app_secret'] ?? '')),
            401,
            'Invalid webhook signature.'
        );

        foreach ($this->events($request->all()) as $event) {
            $igsid = $event['sender']['id'] ?? null;
            $text = $event['message']['text'] ?? null;

            if (! $igsid || ! $text || ($event['message']['is_echo'] ?? false)) {
                continue;
            }

            $contact = Contact::firstOrCreate(
                ['external_id' => $igsid, 'source' => 'instagram'],
                ['display_name' => 'Instagram user', 'status' => 'active'],
            );

            $service->receive($channel, $contact, [
                'body' => $text,
                'external_id' => $event['message']['mid'] ?? null,
                'sent_at' => isset($event['timestamp']) ? now()->setTimestamp((int) round($event['timestamp'] / 1000)) : now(),
                'message_type' => 'text',
            ]);
        }

        return response()->noContent();
    }

    /** @return array<int, array<string, mixed>> */
    private function events(array $payload): array
    {
        $events = [];

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['messaging'] ?? [] as $event) {
                $events[] = $event;
            }
        }

        return $events;
    }
}
