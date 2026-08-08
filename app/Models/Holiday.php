<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Holiday extends Model
{
    protected $fillable = [
        'name', 'date', 'is_full_day', 'starts_at', 'ends_at', 'recurrence', 'country',
        'description', 'department_id', 'team_id', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_full_day' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
