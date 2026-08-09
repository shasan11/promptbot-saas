<?php

namespace App\Services\Customer;

use App\Models\Customer\Company;
use App\Models\Customer\Contact;
use App\Models\Customer\CustomerActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CustomerTimelineService
{
    public function record(
        string $eventType,
        string $description,
        ?Contact $contact = null,
        ?Company $company = null,
        ?User $actor = null,
        ?Model $related = null,
        array $metadata = [],
    ): CustomerActivity {
        $actor ??= request()->user('tenant');

        return CustomerActivity::create([
            'contact_id' => $contact?->id,
            'company_id' => $company?->id ?? $contact?->company_id,
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'event_type' => $eventType,
            'description' => $description,
            'related_type' => $related ? $related::class : null,
            'related_id' => $related?->getKey(),
            'related_label' => $related?->getAttribute('display_name') ?? $related?->getAttribute('name'),
            'metadata' => $metadata ?: null,
            'occurred_at' => now(),
        ]);
    }
}
