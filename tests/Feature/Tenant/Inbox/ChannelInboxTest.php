<?php

namespace Tests\Feature\Tenant\Inbox;

use App\Models\Channel\Channel;
use App\Models\Customer\Contact;
use App\Models\Inbox\Conversation;
use App\Models\Inbox\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class ChannelInboxTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->cleanUpTenants(); parent::tearDown();
    }

    public function test_email_and_web_chat_create_idempotent_tenant_scoped_inbox_conversations(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant, ['name' => 'Inbox Admin'], 'Tenant Administrator');

        $emailResponse = $this->actingAs($admin, 'tenant')->post("http://{$domain}/channels", [
            'type'=>'email','name'=>'Support Email','status'=>'active','team_id'=>null,'default_assignee_id'=>null,'business_hours_policy_id'=>null,'auto_reply_enabled'=>false,'signature'=>'',
            'email'=>['inbox_address'=>'support@example.test','incoming_provider'=>'webhook','outgoing_provider'=>'laravel_mail','from_name'=>'Support','reply_to_address'=>null],
            'credentials'=>['host'=>'','port'=>587,'username'=>'','password'=>'','encryption'=>'tls'],
        ]);
        $emailResponse->assertRedirect()->assertSessionHas('channel_secret');

        tenancy()->initialize($tenant);
        $emailChannel = Channel::where('type','email')->firstOrFail();
        $secret = $emailChannel->credential->encrypted_payload['inbound_secret'];
        $rawCredential = DB::table('channel_credentials')->where('channel_id',$emailChannel->id)->value('encrypted_payload');
        $this->assertStringNotContainsString($secret, $rawCredential);
        tenancy()->end();

        $payload=['sender_email'=>'customer@example.test','sender_name'=>'Customer One','subject'=>'Need help','body'=>'My order is delayed.','message_id'=>'provider-message-1','thread_reference'=>'thread-1'];
        $json=json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature=hash_hmac('sha256',$json,$secret);
        $this->call('POST',"http://{$domain}/channels/email/{$emailChannel->public_uuid}/inbound",[],[],[],['CONTENT_TYPE'=>'application/json','HTTP_X_PROMPTBOT_SIGNATURE'=>$signature],$json)->assertAccepted();
        $this->call('POST',"http://{$domain}/channels/email/{$emailChannel->public_uuid}/inbound",[],[],[],['CONTENT_TYPE'=>'application/json','HTTP_X_PROMPTBOT_SIGNATURE'=>$signature],$json)->assertAccepted();

        tenancy()->initialize($tenant);
        $this->assertSame(1, Conversation::count());
        $this->assertSame(1, Message::where('channel_message_id','provider-message-1')->count());
        $this->assertSame(1, Contact::where('email','customer@example.test')->count());
        tenancy()->end();

        $this->actingAs($admin,'tenant')->get("http://{$domain}/inbox")->assertOk()->assertInertia(fn($page)=>$page->has('conversations.data',1));

        $this->actingAs($admin,'tenant')->post("http://{$domain}/channels", [
            'type'=>'web_chat','name'=>'Main Website Chat','status'=>'active','team_id'=>null,'default_assignee_id'=>null,'business_hours_policy_id'=>null,'auto_reply_enabled'=>false,'signature'=>'',
            'widget'=>['widget_name'=>'Website Support','primary_color'=>'#2563eb','launcher_position'=>'right','welcome_message'=>'Welcome','offline_message'=>'Offline','supported_languages'=>['en'],'allowed_origins'=>['https://shop.example.test'],'privacy_url'=>null,'terms_url'=>null,'allow_attachments'=>true,'require_email'=>true,'require_name'=>true],
        ])->assertRedirect();

        tenancy()->initialize($tenant); $widget=Channel::where('type','web_chat')->firstOrFail()->webChatWidget; tenancy()->end();
        $headers=['Origin'=>'https://shop.example.test'];
        $this->withHeaders([
            'Origin' => 'https://shop.example.test',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'authorization,content-type',
        ])->options("http://{$domain}/widget/api/{$widget->public_key}/session")
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'https://shop.example.test');
        $session=$this->withHeaders($headers)->postJson("http://{$domain}/widget/api/{$widget->public_key}/session",['name'=>'Chat Visitor','email'=>'visitor@example.test','locale'=>'en'])->assertCreated()->json();
        $this->withHeaders(array_merge($headers,['Authorization'=>'Bearer '.$session['token']]))->postJson("http://{$domain}/widget/api/{$widget->public_key}/messages",['body'=>'Hello from the widget','client_id'=>'client-1'])->assertCreated();

        tenancy()->initialize($tenant);
        $this->assertTrue(Conversation::whereHas('contact',fn($q)=>$q->where('email','visitor@example.test'))->exists());
        tenancy()->end();
    }

    public function test_web_chat_without_allowed_origins_accepts_any_embedding_origin(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant, ['name' => 'Widget Admin'], 'Tenant Administrator');

        $this->actingAs($admin, 'tenant')->post("http://{$domain}/channels", [
            'type' => 'web_chat', 'name' => 'Open Website Chat', 'status' => 'active',
            'team_id' => null, 'default_assignee_id' => null, 'business_hours_policy_id' => null,
            'auto_reply_enabled' => false, 'signature' => '',
            'widget' => [
                'widget_name' => 'Open Chat', 'primary_color' => '#2563eb', 'launcher_position' => 'right',
                'welcome_message' => 'Welcome', 'offline_message' => 'Offline', 'supported_languages' => ['en'],
                'allowed_origins' => [], 'privacy_url' => null, 'terms_url' => null,
                'allow_attachments' => true, 'require_email' => true, 'require_name' => true,
            ],
        ])->assertRedirect();

        tenancy()->initialize($tenant);
        $widget = Channel::where('type', 'web_chat')->firstOrFail()->webChatWidget;
        tenancy()->end();

        $origin = 'https://any-customer-site.example';
        $this->withHeaders([
            'Origin' => $origin,
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'content-type',
        ])->options("http://{$domain}/widget/api/{$widget->public_key}/session")
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', $origin);

        $this->withHeader('Origin', $origin)
            ->getJson("http://{$domain}/widget/api/{$widget->public_key}/config")
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', $origin);
    }
}
