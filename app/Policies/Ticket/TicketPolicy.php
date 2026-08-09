<?php
namespace App\Policies\Ticket; use App\Models\Ticket\Ticket; use App\Models\User;
class TicketPolicy { public function viewAny(User $u):bool{return $u->can('tickets.view');} public function view(User $u,Ticket $t):bool{return $u->can('tickets.view');} public function create(User $u):bool{return $u->can('tickets.create');} public function update(User $u,Ticket $t):bool{return $u->can('tickets.update');} public function delete(User $u,Ticket $t):bool{return $u->can('tickets.delete');} public function merge(User $u,Ticket $t):bool{return $u->can('tickets.merge');} }
