<?php
namespace App\Models\Sla; use App\Models\Concerns\HasPublicUuid; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class EscalationExecution extends Model {use HasPublicUuid;protected $fillable=['escalation_policy_id','resource_type','resource_id','status','actions_executed','error_message','executed_at'];protected function casts():array{return ['actions_executed'=>'array','executed_at'=>'datetime'];}public function policy():BelongsTo{return $this->belongsTo(EscalationPolicy::class,'escalation_policy_id');}}
