<?php
namespace Tests\Feature\Tenant\Ticket;
use App\Models\Customer\Contact; use App\Models\Task\Task; use App\Models\Ticket\Ticket; use App\Models\Ticket\TicketStatus; use Illuminate\Foundation\Testing\RefreshDatabase; use Tests\Concerns\InteractsWithTenancy; use Tests\TestCase;
class TicketTaskTest extends TestCase
{
 use InteractsWithTenancy,RefreshDatabase;
 protected function tearDown():void{$this->cleanUpTenants();parent::tearDown();}
 public function test_ticket_numbering_merge_activity_permissions_and_related_subtasks():void
 {
  [$tenant,$domain]=$this->createTenantWithDomain();[$admin,$noAccess]=$this->createTenantUsers($tenant,[['attributes'=>['name'=>'Ticket Admin'],'role'=>'Tenant Administrator'],['attributes'=>['name'=>'No Ticket Access'],'role'=>null]]);
  $this->actingAs($noAccess,'tenant')->get("http://{$domain}/tickets")->assertForbidden();
  tenancy()->initialize($tenant);$contact=Contact::create(['display_name'=>'Ticket Customer','email'=>'ticket@example.test','status'=>'active','source'=>'manual']);$status=TicketStatus::where('is_default',true)->firstOrFail();tenancy()->end();
  $payload=['subject'=>'First issue','description'=>'Details','contact_id'=>$contact->id,'company_id'=>null,'conversation_id'=>null,'channel_id'=>null,'team_id'=>null,'assignee_id'=>$admin->id,'priority'=>'high','status_id'=>$status->id,'category_id'=>null,'source'=>'manual','tag_ids'=>[],'custom_fields'=>[]];
  $this->actingAs($admin,'tenant')->post("http://{$domain}/tickets",$payload)->assertRedirect();
  $this->actingAs($admin,'tenant')->post("http://{$domain}/tickets",array_merge($payload,['subject'=>'Duplicate issue']))->assertRedirect();
  tenancy()->initialize($tenant);$tickets=Ticket::orderBy('id')->get();$this->assertSame('TKT-000001',$tickets[0]->ticket_number);$this->assertSame('TKT-000002',$tickets[1]->ticket_number);tenancy()->end();
  $this->actingAs($admin,'tenant')->post("http://{$domain}/tickets/{$tickets[1]->public_uuid}/merge",['destination_id'=>$tickets[0]->id,'reason'=>'Duplicate'])->assertRedirect();
  tenancy()->initialize($tenant);$this->assertSame($tickets[0]->id,$tickets[1]->fresh()->merged_into_id);$this->assertTrue($tickets[0]->activities()->where('event_type','ticket.merge_received')->exists());tenancy()->end();
  $taskPayload=['title'=>'Follow up','description'=>'Call customer','status'=>'todo','priority'=>'normal','assigned_to'=>$admin->id,'due_at'=>now()->addDay()->format('Y-m-d H:i:s'),'related_type'=>'ticket','related_id'=>$tickets[0]->id,'parent_task_id'=>null,'tag_ids'=>[]];
  $this->actingAs($admin,'tenant')->post("http://{$domain}/tasks",$taskPayload)->assertRedirect();
  tenancy()->initialize($tenant);$parent=Task::firstOrFail();tenancy()->end();
  $this->actingAs($admin,'tenant')->post("http://{$domain}/tasks",array_merge($taskPayload,['title'=>'Subtask','parent_task_id'=>$parent->id]))->assertRedirect();
  tenancy()->initialize($tenant);$this->assertSame(1,$parent->subtasks()->count());tenancy()->end();
 }
}
