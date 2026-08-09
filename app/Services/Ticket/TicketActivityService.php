<?php
namespace App\Services\Ticket; use App\Models\Ticket\Ticket; use App\Models\Ticket\TicketActivity; use App\Models\User;
class TicketActivityService { public function record(Ticket $ticket,string $event,string $description,?User $actor=null,array $old=[],array $new=[],array $metadata=[]):TicketActivity { $actor??=request()->user('tenant'); return $ticket->activities()->create(['actor_id'=>$actor?->id,'actor_name'=>$actor?->name,'event_type'=>$event,'description'=>$description,'old_values'=>$old?:null,'new_values'=>$new?:null,'metadata'=>$metadata?:null,'occurred_at'=>now()]); } }
