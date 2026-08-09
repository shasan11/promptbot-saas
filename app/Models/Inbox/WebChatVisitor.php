<?php
namespace App\Models\Inbox;
use App\Models\Channel\WebChatWidget; use App\Models\Concerns\HasPublicUuid; use App\Models\Customer\Contact; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class WebChatVisitor extends Model { use HasPublicUuid; protected $fillable=['web_chat_widget_id','contact_id','session_token_hash','locale','ip_hash','metadata','last_seen_at','expires_at']; protected function casts():array{return ['metadata'=>'array','last_seen_at'=>'datetime','expires_at'=>'datetime'];} public function widget():BelongsTo{return $this->belongsTo(WebChatWidget::class,'web_chat_widget_id');} public function contact():BelongsTo{return $this->belongsTo(Contact::class);} }
