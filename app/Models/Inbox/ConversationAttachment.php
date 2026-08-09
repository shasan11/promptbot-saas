<?php
namespace App\Models\Inbox;
use App\Models\Concerns\HasPublicUuid; use App\Models\User; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ConversationAttachment extends Model { use HasPublicUuid; protected $fillable = ['message_id','original_filename','stored_filename','mime_type','file_size','storage_disk','storage_path','uploaded_by','checksum']; public function message(): BelongsTo{return $this->belongsTo(Message::class);} public function uploader(): BelongsTo{return $this->belongsTo(User::class,'uploaded_by');} }
