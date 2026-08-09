<?php
namespace Tests\Feature\Tenant\Commercial;
use App\Models\Customer\Contact;use App\Models\Ticket\Ticket;use App\Models\Ticket\TicketStatus;use Illuminate\Foundation\Testing\RefreshDatabase;use Illuminate\Support\Facades\DB;use Tests\Concerns\InteractsWithTenancy;use Tests\TestCase;
class HelpdeskCommercialSurfaceTest extends TestCase
{
 use InteractsWithTenancy,RefreshDatabase;protected function tearDown():void{$this->cleanUpTenants();parent::tearDown();}
 public function test_commercial_helpdesk_surfaces_are_tenant_scoped_and_operational():void
 {
  [$tenant,$domain]=$this->createTenantWithDomain();[$admin]=$this->createTenantUsers($tenant,[['attributes'=>['name'=>'Workspace Owner'],'role'=>'Tenant Administrator']]);
  foreach(['/operations','/automation','/experience','/reports','/developer','/quality','/workforce','/notifications'] as $path)$this->actingAs($admin,'tenant')->get("http://{$domain}{$path}")->assertOk();
  $this->actingAs($admin,'tenant')->post("http://{$domain}/automation/rules",['name'=>'Raise new tickets','trigger'=>'ticket.created','resource_type'=>'ticket','priority'=>10,'field'=>'resource.priority','operator'=>'equals','condition_value'=>'normal','action_type'=>'set_priority','action_value'=>'urgent'])->assertRedirect();
  $this->actingAs($admin,'tenant')->post("http://{$domain}/operations/sla",['name'=>'Default SLA','calendar_mode'=>'24_7','business_hours_policy_id'=>null,'approaching_percentage'=>80,'pause_statuses'=>['pending'],'targets'=>['normal'=>['first_response'=>60,'next_response'=>120,'resolution'=>480],'urgent'=>['first_response'=>10,'next_response'=>30,'resolution'=>120]]])->assertRedirect();
  tenancy()->initialize($tenant);$contact=Contact::create(['display_name'=>'Commercial Customer','email'=>'commercial@example.test','status'=>'active','source'=>'manual']);$status=TicketStatus::where('is_default',true)->firstOrFail();tenancy()->end();
  $payload=['subject'=>'Commercial workflow','description'=>'Verify deterministic automation.','contact_id'=>$contact->id,'company_id'=>null,'conversation_id'=>null,'channel_id'=>null,'team_id'=>null,'assignee_id'=>$admin->id,'priority'=>'normal','status_id'=>$status->id,'category_id'=>null,'source'=>'manual','tag_ids'=>[],'custom_fields'=>[]];
  $this->actingAs($admin,'tenant')->post("http://{$domain}/tickets",$payload)->assertRedirect();
  tenancy()->initialize($tenant);$ticket=Ticket::firstOrFail();$this->assertSame('urgent',$ticket->priority);$this->assertTrue(DB::table('automation_logs')->where('resource_type','ticket')->where('resource_id',$ticket->id)->where('status','completed')->exists());$this->assertTrue(DB::table('sla_instances')->where('resource_type','ticket')->where('resource_id',$ticket->id)->exists());tenancy()->end();
  $this->actingAs($admin,'tenant')->post("http://{$domain}/experience/forms",['name'=>'Contact support','slug'=>'contact-support','description'=>'Public support form'])->assertRedirect();
  $this->post("http://{$domain}/forms/contact-support",['email'=>'form@example.test','subject'=>'Form issue','message'=>'Please help.'])->assertRedirect();
  tenancy()->initialize($tenant);$this->assertTrue(DB::table('support_form_submissions')->where('status','received')->exists());tenancy()->end();
  $keyResponse=$this->actingAs($admin,'tenant')->post("http://{$domain}/developer/api-keys",['name'=>'Test API','scopes'=>['contacts.read']])->assertRedirect()->assertSessionHas('api_key');$plain=$keyResponse->getSession()->get('api_key');
  $this->withToken($plain)->getJson("http://{$domain}/tenant-api/v1/contacts")->assertOk()->assertJsonStructure(['data']);
  $this->actingAs($admin,'tenant')->post("http://{$domain}/quality/scorecards",['name'=>'Standard quality','criteria'=>"Accuracy\nTone\nResolution",'passing_score'=>80])->assertRedirect();
  $this->actingAs($admin,'tenant')->post("http://{$domain}/workforce/shifts",['user_id'=>$admin->id,'starts_at'=>now()->addDay()->format('Y-m-d H:i:s'),'ends_at'=>now()->addDay()->addHours(8)->format('Y-m-d H:i:s'),'timezone'=>'UTC','notes'=>'Coverage'])->assertRedirect();
  tenancy()->initialize($tenant);$this->assertSame(1,DB::table('qa_scorecards')->count());$this->assertSame(1,DB::table('workforce_shifts')->count());$this->assertSame(1,DB::table('api_request_logs')->count());tenancy()->end();
 }
}
