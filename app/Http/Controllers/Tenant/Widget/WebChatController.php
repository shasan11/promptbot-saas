<?php

namespace App\Http\Controllers\Tenant\Widget;

use App\Http\Controllers\Controller;
use App\Models\Channel\WebChatWidget;
use App\Models\Customer\Contact;
use App\Models\Inbox\Conversation;
use App\Models\Inbox\WebChatVisitor;
use App\Services\Channels\WebChatAutoReplyService;
use App\Services\Inbox\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class WebChatController extends Controller
{
    // Revalidate-on-every-load rather than a flat max-age: an hour-long cache
    // with no ETag/Last-Modified check is exactly what let a fixed bug's old,
    // broken copy keep running in already-open visitor tabs. Conditional
    // GETs from Symfony's file response still avoid re-transferring the body
    // when nothing changed, so this costs a 304 round trip, not a full refetch.
    public function script(Request $request): BinaryFileResponse
    {
        $response = response()->file(public_path('promptbot-widget.js'), ['Content-Type' => 'application/javascript; charset=UTF-8', 'Cache-Control' => 'no-cache, must-revalidate']);
        $response->isNotModified($request);

        return $response;
    }

    public function config(Request $request, string $key): JsonResponse
    {
        $widget = $this->widget($request, $key);
        return $this->cors($request, response()->json(['name'=>$widget->widget_name,'primaryColor'=>$widget->primary_color,'position'=>$widget->launcher_position,'welcomeMessage'=>$widget->welcome_message,'offlineMessage'=>$widget->offline_message,'requireName'=>$widget->require_name,'requireEmail'=>$widget->require_email,'allowAttachments'=>$widget->allow_attachments,'privacyUrl'=>$widget->privacy_url,'termsUrl'=>$widget->terms_url]));
    }

    public function session(Request $request, string $key): JsonResponse
    {
        $widget=$this->widget($request,$key); $data=$request->validate(['name'=>[$widget->require_name?'required':'nullable','string','max:255'],'email'=>[$widget->require_email?'required':'nullable','email:rfc','max:255'],'locale'=>['nullable','string','max:12']]);
        $contact=!empty($data['email'])?Contact::firstOrCreate(['email'=>Str::lower($data['email'])],['display_name'=>$data['name']??$data['email'],'first_name'=>$data['name']??null,'status'=>'active','source'=>'web_chat']):Contact::create(['display_name'=>$data['name']??'Website visitor','status'=>'active','source'=>'web_chat']);
        $token=Str::random(64); WebChatVisitor::create(['web_chat_widget_id'=>$widget->id,'contact_id'=>$contact->id,'session_token_hash'=>hash('sha256',$token),'locale'=>$data['locale']??null,'ip_hash'=>hash_hmac('sha256',(string)$request->ip(),config('app.key')),'expires_at'=>now()->addDays(30),'last_seen_at'=>now()]);
        return $this->cors($request,response()->json(['token'=>$token,'visitor'=>$contact->public_uuid],201));
    }

    public function messages(Request $request, string $key, ConversationService $service, WebChatAutoReplyService $autoReply): JsonResponse
    {
        $widget=$this->widget($request,$key); $visitor=$this->visitor($request,$widget); $data=$request->validate(['body'=>['required','string','max:10000'],'client_id'=>['required','string','max:120']]);
        $message=$service->receive($widget->channel,$visitor->contact,['body'=>$data['body'],'external_id'=>'widget:'.$data['client_id'],'message_type'=>'text']); $visitor->update(['last_seen_at'=>now()]);
        $autoReply->maybeReply($widget, $message->conversation, $data['body']);
        // 'id' must stay the numeric DB id, matching poll()'s shape: the widget
        // tracks the highest id it has rendered to ask for only newer messages
        // next time. Sending public_uuid here previously made that Math.max()
        // computation NaN, which silently disabled the poll filter and made
        // every subsequent poll re-send (and re-render) the full history.
        return $this->cors($request,response()->json(['message'=>['id'=>$message->id,'uuid'=>$message->public_uuid,'body'=>$message->body,'direction'=>$message->direction,'sentAt'=>$message->sent_at]],201));
    }

    public function poll(Request $request, string $key): JsonResponse
    {
        $widget=$this->widget($request,$key); $visitor=$this->visitor($request,$widget); $conversation=Conversation::query()->where('channel_id',$widget->channel_id)->where('contact_id',$visitor->contact_id)->latest('last_message_at')->first();
        // Always apply the id filter, never conditionally: when() skipped it
        // whenever $after was falsy (0, or an unparseable value cast to 0 by
        // ->integer()), which is exactly what let a malformed `after` value
        // silently turn every poll into "return the entire history."
        $after=max(0,$request->integer('after'));
        $messages=$conversation?->messages()->where('direction','!=','internal')->where('id','>',$after)->orderBy('id')->limit(100)->get(['id','public_uuid','body','direction','status','sent_at'])??collect();
        return $this->cors($request,response()->json(['messages'=>$messages]));
    }

    private function widget(Request $request,string $key): WebChatWidget
    {
        $widget=WebChatWidget::query()->with('channel')->where('public_key',$key)->where('active',true)->whereHas('channel',fn($q)=>$q->where('status','active'))->firstOrFail(); $origin=$request->headers->get('Origin');
        if ($origin && ($widget->allowed_origins??[])!==[] && !in_array(rtrim($origin,'/'),array_map(fn($x)=>rtrim($x,'/'),$widget->allowed_origins),true)) abort(403,'Origin is not allowed.'); return $widget;
    }
    private function visitor(Request $request,WebChatWidget $widget): WebChatVisitor
    {
        $token=$request->bearerToken(); abort_unless($token,401); return WebChatVisitor::query()->with('contact')->where('web_chat_widget_id',$widget->id)->where('session_token_hash',hash('sha256',$token))->where('expires_at','>',now())->firstOrFail();
    }
    private function cors(Request $request,JsonResponse $response): JsonResponse
    {
        if($origin=$request->headers->get('Origin'))$response->headers->set('Access-Control-Allow-Origin',$origin); $response->headers->set('Vary','Origin'); return $response;
    }
}
