<?php
namespace App\Models\Operations; use App\Models\Concerns\HasPublicUuid; use Illuminate\Database\Eloquent\Model;
class AssignmentRule extends Model {use HasPublicUuid;protected $fillable=['name','resource_type','priority','active','conditions','actions','stop_processing','created_by'];protected function casts():array{return ['active'=>'boolean','conditions'=>'array','actions'=>'array','stop_processing'=>'boolean'];}}
