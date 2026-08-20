<?php

namespace App\Http\Controllers\Tenant\Channel;

use App\Http\Controllers\Controller;
use App\Models\Channel\Channel;
use App\Models\Customer\Contact;
use App\Services\Inbox\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InboundTelegramController extends Controller
{
    public function __invoke(Request $request, Channel $channel, ConversationService $service): JsonResponse
    {
        abort_unless($channel->type === 'telegram' && $channel->status === 'active', 404);

        $secret = $channel->credential?->encrypted_payload['webhook_secret'] ?? null;
        $header = $request->header('X-Telegram-Bot-Api-Secret-Token');

        abort_unless($secret && $header && hash_equals($secret, $header), 401, 'Invalid webhook secret.');

        $message = $request->input('message') ?? $request->input('edited_message');
        $chatId = $message['chat']['id'] ?? null;
        $text = $message['text'] ?? null;

        if ($chatId && $text) {
            $from = $message['from'] ?? [];
            $displayName = trim(($from['first_name'] ?? '').' '.($from['last_name'] ?? '')) ?: ($from['username'] ?? 'Telegram user');

            $contact = Contact::firstOrCreate(
                ['external_id' => (string) $chatId, 'source' => 'telegram'],
                ['display_name' => $displayName, 'status' => 'active'],
            );

            $service->receive($channel, $contact, [
                'body' => $text,
                'external_id' => (string) ($message['message_id'] ?? ''),
                'sent_at' => isset($message['date']) ? now()->setTimestamp((int) $message['date']) : now(),
                'message_type' => 'text',
            ]);
        }

        return response()->json(['ok' => true]);
    }
}
