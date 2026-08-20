<?php

namespace App\Http\Controllers\Tenant\Channel;

use App\Http\Controllers\Controller;
use App\Models\Channel\Channel;
use App\Models\Customer\Contact;
use App\Services\Channels\Support\MetaWebhookVerifier;
use App\Services\Inbox\ConversationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InboundWhatsappController extends Controller
{
    public function __invoke(Request $request, Channel $channel, ConversationService $service, MetaWebhookVerifier $verifier): Response
    {
        abort_unless($channel->type === 'whatsapp' && $channel->status === 'active', 404);

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

        foreach ($this->messages($request->all()) as $message) {
            $waId = $message['from'] ?? null;

            if (! $waId || ! isset($message['text']['body'])) {
                continue;
            }

            $contact = Contact::firstOrCreate(
                ['phone' => $waId],
                ['display_name' => $waId, 'status' => 'active', 'source' => 'whatsapp'],
            );

            $service->receive($channel, $contact, [
                'body' => $message['text']['body'],
                'external_id' => $message['id'] ?? null,
                'sent_at' => isset($message['timestamp']) ? now()->setTimestamp((int) $message['timestamp']) : now(),
                'message_type' => 'text',
                'metadata' => ['whatsapp_message_type' => $message['type'] ?? 'text'],
            ]);
        }

        return response()->noContent();
    }

    /** @return array<int, array<string, mixed>> */
    private function messages(array $payload): array
    {
        $messages = [];

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                foreach ($change['value']['messages'] ?? [] as $message) {
                    $messages[] = $message;
                }
            }
        }

        return $messages;
    }
}
