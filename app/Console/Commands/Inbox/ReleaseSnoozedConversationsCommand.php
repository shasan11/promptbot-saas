<?php
namespace App\Console\Commands\Inbox;
use App\Enums\TenantStatus; use App\Models\Inbox\Conversation; use App\Models\Tenant; use Illuminate\Console\Command; use Throwable;
class ReleaseSnoozedConversationsCommand extends Command
{
    protected $signature='inbox:release-snoozed {--tenant=* : Limit to tenant IDs}'; protected $description='Reopen tenant conversations whose snooze period has elapsed';
    public function handle():int
    {
        $tenants=Tenant::query()->where('status',TenantStatus::Active)->when($this->option('tenant'),fn($q,$ids)=>$q->whereIn('id',$ids))->get(); $released=0;
        foreach($tenants as $tenant){try{tenancy()->initialize($tenant);$released+=Conversation::query()->where('status','snoozed')->where('snoozed_until','<=',now())->update(['status'=>'open','snoozed_until'=>null,'updated_at'=>now()]);}catch(Throwable $e){$this->error("{$tenant->id}: {$e->getMessage()}");}finally{if(tenancy()->initialized)tenancy()->end();}}
        $this->info("Reopened {$released} snoozed conversation(s)."); return self::SUCCESS;
    }
}
