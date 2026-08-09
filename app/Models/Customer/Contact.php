<?php

namespace App\Models\Customer;

use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use App\Models\Inbox\Conversation;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use HasPublicUuid, SoftDeletes;

    protected $fillable = [
        'first_name', 'last_name', 'display_name', 'email', 'secondary_email', 'phone',
        'secondary_phone', 'country', 'timezone', 'preferred_language', 'avatar_path',
        'status', 'source', 'owner_id', 'company_id', 'external_id', 'last_contacted_at',
        'last_seen_at', 'metadata', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'last_contacted_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function contactPoints(): HasMany { return $this->hasMany(ContactPoint::class); }
    public function activities(): HasMany { return $this->hasMany(CustomerActivity::class)->latest('occurred_at'); }
    public function conversations(): HasMany { return $this->hasMany(Conversation::class); }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable')->withPivot(['assigned_by', 'created_at']);
    }

    public function customFieldValues(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class, 'resource_id')
            ->where('resource_type', 'contact');
    }
}
