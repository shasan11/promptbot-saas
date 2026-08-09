<?php
namespace App\Models\Inbox;
use App\Models\Team; use App\Models\User; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class MessageMention extends Model { public $timestamps=false; protected $fillable=['message_id','user_id','team_id','read_at','created_at']; protected function casts():array{return ['read_at'=>'datetime','created_at'=>'datetime'];} public function message():BelongsTo{return $this->belongsTo(Message::class);} public function user():BelongsTo{return $this->belongsTo(User::class);} public function team():BelongsTo{return $this->belongsTo(Team::class);} }
