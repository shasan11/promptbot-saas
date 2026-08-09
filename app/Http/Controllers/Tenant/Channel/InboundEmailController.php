<?php

namespace App\Http\Controllers\Tenant\Channel;

use App\Http\Controllers\Controller;
use App\Models\Channel\Channel;
use App\Models\Customer\Contact;
use App\Services\Inbox\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InboundEmailController extends Controller
{
    public function __invoke(Request $request, Channel $channel, ConversationService $service): JsonResponse
    {
        abort_unless($channel->type === 'email' && $channel->status === 'active', 404);
        $secret = $channel->credential?->encrypted_payload['inbound_secret'] ?? null;
        $signature = $request->header('X-PromptBot-Signature');
        abort_unless($secret && $signature && hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $signature), 401, 'Invalid webhook signature.');
        $data = $request->validate(['sender_email'=>['required','email:rfc'],'sender_name'=>['nullable','string','max:255'],'subject'=>['nullable','string','max:998'],'body'=>['required','string','max:200000'],'message_id'=>['required','string','max:998'],'thread_reference'=>['nullable','string','max:998'],'sent_at'=>['nullable','date'],'metadata'=>['nullable','array']]);
        $email=Str::lower($data['sender_email']); $contact=Contact::firstOrCreate(['email'=>$email],['display_name'=>$data['sender_name']??$email,'first_name'=>$data['sender_name']??null,'status'=>'active','source'=>'email']);
        $message=$service->receive($channel,$contact,['body'=>$data['body'],'subject'=>$data['subject']??null,'external_id'=>$data['message_id'],'thread_reference'=>$data['thread_reference']??null,'sent_at'=>$data['sent_at']??now(),'message_type'=>'email','metadata'=>$data['metadata']??[]]);
        return response()->json(['message'=>'Accepted.','id'=>$message->public_uuid],202);
    }
}
