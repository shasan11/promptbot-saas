<?php

namespace App\Policies\Channel;

use App\Models\Channel\BotProfile;
use App\Models\User;

/**
 * Bot profiles are channel configuration, so they reuse the `channels.*`
 * permissions rather than introducing a new set.
 *
 * A new permission would be absent from every role in every existing install
 * until its tenant re-ran the authorization seeder — which, for a self-hosted
 * product, means the screen would silently 403 for administrators after an
 * update. Anyone who can already configure a channel can already change how
 * its bot behaves through the channel form, so this grants nothing new.
 */
class BotProfilePolicy
{
    public function viewAny(User $user): bool { return $user->can('channels.view'); }
    public function view(User $user, BotProfile $profile): bool { return $user->can('channels.view'); }
    public function create(User $user): bool { return $user->can('channels.create'); }
    public function update(User $user, BotProfile $profile): bool { return $user->can('channels.update'); }
    public function delete(User $user, BotProfile $profile): bool { return $user->can('channels.delete'); }
}
