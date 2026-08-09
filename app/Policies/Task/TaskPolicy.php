<?php
namespace App\Policies\Task; use App\Models\Task\Task; use App\Models\User;
class TaskPolicy { public function viewAny(User $u):bool{return $u->can('tasks.view');} public function view(User $u,Task $t):bool{return $u->can('tasks.view');} public function create(User $u):bool{return $u->can('tasks.create');} public function update(User $u,Task $t):bool{return $u->can('tasks.update');} public function delete(User $u,Task $t):bool{return $u->can('tasks.delete');} }
