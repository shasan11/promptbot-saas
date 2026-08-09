<?php

namespace App\Models\Customer;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactPoint extends Model
{
    protected $fillable = ['type', 'label', 'value', 'normalized_value', 'is_primary', 'is_verified', 'metadata'];
    protected function casts(): array { return ['is_primary' => 'boolean', 'is_verified' => 'boolean', 'metadata' => 'array']; }
    public function contact(): BelongsTo { return $this->belongsTo(Contact::class); }
}
