<?php

namespace App\Models\Customer;

use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerActivity extends Model
{
    use HasPublicUuid;

    protected $fillable = ['contact_id', 'company_id', 'actor_id', 'actor_name', 'event_type', 'description', 'related_type', 'related_id', 'related_label', 'metadata', 'occurred_at'];
    protected function casts(): array { return ['metadata' => 'array', 'occurred_at' => 'datetime']; }
    public function contact(): BelongsTo { return $this->belongsTo(Contact::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}
