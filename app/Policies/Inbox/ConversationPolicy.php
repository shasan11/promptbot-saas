<?php
namespace App\Policies\Inbox;
use App\Models\Inbox\Conversation; use App\Models\User;
class ConversationPolicy { public function viewAny(User $u):bool{return $u->can('inbox.view');} public function view(User $u,Conversation $c):bool{return $u->can('inbox.view');} public function reply(User $u,Conversation $c):bool{return $u->can('inbox.reply');} public function update(User $u,Conversation $c):bool{return $u->can('inbox.update');} public function assign(User $u,Conversation $c):bool{return $u->can('inbox.assign');} }
