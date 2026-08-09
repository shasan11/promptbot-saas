<?php

namespace App\Policies\Channel;

use App\Models\Channel\Channel;
use App\Models\User;

class ChannelPolicy
{
    public function viewAny(User $user): bool { return $user->can('channels.view'); }
    public function view(User $user, Channel $channel): bool { return $user->can('channels.view'); }
    public function create(User $user): bool { return $user->can('channels.create'); }
    public function update(User $user, Channel $channel): bool { return $user->can('channels.update'); }
    public function delete(User $user, Channel $channel): bool { return $user->can('channels.delete'); }
}
