<?php
namespace App\Models\Inbox;
use App\Models\Team; use App\Models\User; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ConversationAssignment extends Model { public $timestamps=false; protected $fillable=['conversation_id','from_user_id','to_user_id','from_team_id','to_team_id','assigned_by','reason','created_at']; protected function casts():array{return ['created_at'=>'datetime'];} public function conversation():BelongsTo{return $this->belongsTo(Conversation::class);} public function actor():BelongsTo{return $this->belongsTo(User::class,'assigned_by');} }
