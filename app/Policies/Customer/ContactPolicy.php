<?php

namespace App\Policies\Customer;

use App\Models\Customer\Contact;
use App\Models\User;

class ContactPolicy
{
    public function viewAny(User $user): bool { return $user->can('customers.view'); }
    public function view(User $user, Contact $contact): bool { return $user->can('customers.view'); }
    public function create(User $user): bool { return $user->can('customers.create'); }
    public function update(User $user, Contact $contact): bool { return $user->can('customers.update'); }
    public function delete(User $user, Contact $contact): bool { return $user->can('customers.delete'); }
    public function restore(User $user, Contact $contact): bool { return $user->can('customers.restore'); }
}
