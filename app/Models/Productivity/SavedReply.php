<?php
namespace App\Models\Productivity; use App\Models\Concerns\HasPublicUuid; use Illuminate\Database\Eloquent\Model;
class SavedReply extends Model {use HasPublicUuid;protected $fillable=['name','shortcut','scope','team_id','subject','body','variables','active','usage_count','created_by'];protected function casts():array{return['variables'=>'array','active'=>'boolean'];}}
