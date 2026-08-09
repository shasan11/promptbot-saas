<?php
namespace App\Models\Productivity; use App\Models\Concerns\HasPublicUuid; use Illuminate\Database\Eloquent\Model;
class Macro extends Model {use HasPublicUuid;protected $fillable=['name','description','resource_type','scope','team_id','actions','active','created_by'];protected function casts():array{return['actions'=>'array','active'=>'boolean'];}}
