<?php

namespace App\Models\Customer;

use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasPublicUuid, SoftDeletes;

    protected $fillable = [
        'name', 'domain', 'industry', 'website', 'phone', 'address', 'city', 'region',
        'country', 'postal_code', 'account_owner_id', 'status', 'notes', 'metadata', 'created_by',
    ];

    protected function casts(): array { return ['metadata' => 'array']; }

    public function contacts(): HasMany { return $this->hasMany(Contact::class); }
    public function accountOwner(): BelongsTo { return $this->belongsTo(User::class, 'account_owner_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function activities(): HasMany { return $this->hasMany(CustomerActivity::class)->latest('occurred_at'); }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable')->withPivot(['assigned_by', 'created_at']);
    }

    public function customFieldValues(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class, 'resource_id')
            ->where('resource_type', 'company');
    }
}
