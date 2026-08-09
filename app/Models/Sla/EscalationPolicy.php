<?php
namespace App\Models\Sla; use App\Models\Concerns\HasPublicUuid; use Illuminate\Database\Eloquent\Model;
class EscalationPolicy extends Model {use HasPublicUuid;protected $fillable=['name','active','priority','conditions','actions','cooldown_minutes','created_by'];protected function casts():array{return ['active'=>'boolean','conditions'=>'array','actions'=>'array'];}}
