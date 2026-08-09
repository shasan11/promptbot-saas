<?php

namespace App\Models\Customer;

use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomField extends Model
{
    use HasPublicUuid;

    public const RESOURCE_TYPES = ['contact', 'company', 'conversation', 'ticket', 'task'];
    public const FIELD_TYPES = ['text', 'textarea', 'integer', 'decimal', 'currency', 'boolean', 'date', 'datetime', 'email', 'url', 'phone', 'single_select', 'multi_select'];

    protected $fillable = ['label', 'key', 'resource_type', 'field_type', 'required', 'default_value', 'options', 'validation', 'placeholder', 'help_text', 'display_order', 'active', 'created_by'];
    protected function casts(): array { return ['required' => 'boolean', 'default_value' => 'json', 'options' => 'array', 'validation' => 'array', 'active' => 'boolean']; }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function values(): HasMany { return $this->hasMany(CustomFieldValue::class); }
}
