<?php

namespace App\Http\Controllers\Tenant\Channel;

use App\Http\Controllers\Controller;
use App\Models\Channel\Channel;
use App\Models\Customer\Contact;
use App\Services\Channels\Support\TwilioSignatureVerifier;
use App\Services\Inbox\ConversationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InboundSmsController extends Controller
{
    public function __invoke(Request $request, Channel $channel, ConversationService $service, TwilioSignatureVerifier $verifier): Response
    {
        abort_unless($channel->type === 'sms' && $channel->status === 'active', 404);

        $secret = $channel->credential?->encrypted_payload ?? [];

        abort_unless(
            $verifier->verify($request->fullUrl(), $request->post(), $request->header('X-Twilio-Signature'), (string) ($secret['auth_token'] ?? '')),
            401,
            'Invalid webhook signature.'
        );

        $from = $request->input('From');
        $body = $request->input('Body');

        if ($from && $body) {
            $contact = Contact::firstOrCreate(
                ['phone' => $from],
                ['display_name' => $from, 'status' => 'active', 'source' => 'sms'],
            );

            $service->receive($channel, $contact, [
                'body' => $body,
                'external_id' => $request->input('MessageSid'),
                'sent_at' => now(),
                'message_type' => 'text',
            ]);
        }

        return response('', 200);
    }
}
