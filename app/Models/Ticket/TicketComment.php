<?php
namespace App\Models\Ticket; use App\Models\Concerns\HasPublicUuid; use App\Models\User; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class TicketComment extends Model { use HasPublicUuid; protected $fillable=['ticket_id','user_id','author_name','body','internal']; protected function casts():array{return ['internal'=>'boolean'];} public function ticket():BelongsTo{return $this->belongsTo(Ticket::class);} public function user():BelongsTo{return $this->belongsTo(User::class);} }
