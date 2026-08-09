<?php

namespace App\Models\Customer;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomFieldValue extends Model
{
    protected $fillable = ['custom_field_id', 'resource_type', 'resource_id', 'value'];
    protected function casts(): array { return ['value' => 'json']; }
    public function field(): BelongsTo { return $this->belongsTo(CustomField::class, 'custom_field_id'); }
}
