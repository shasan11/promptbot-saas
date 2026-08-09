<?php
namespace App\Events\Inbox;
use App\Models\Inbox\Conversation; use App\Models\Inbox\Message; use Illuminate\Foundation\Events\Dispatchable; use Illuminate\Queue\SerializesModels;
class ConversationReceived { use Dispatchable, SerializesModels; public function __construct(public Conversation $conversation, public Message $message) {} }
