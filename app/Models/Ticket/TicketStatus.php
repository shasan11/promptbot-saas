<?php
namespace App\Models\Ticket; use App\Models\Concerns\HasPublicUuid; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\HasMany;
class TicketStatus extends Model { use HasPublicUuid; protected $fillable=['name','slug','category','color','display_order','is_default','active']; protected function casts():array{return ['is_default'=>'boolean','active'=>'boolean'];} public function tickets():HasMany{return $this->hasMany(Ticket::class,'status_id');} }
