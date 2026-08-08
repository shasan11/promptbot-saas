<?php

namespace App\Models\Connections;

use App\Models\Connections\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectionUsageRecord extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = ['tenant_id', 'connection_id', 'data_source_id', 'connection_action_execution_id', 'usage_type', 'quantity', 'unit', 'bytes', 'metadata', 'usage_date', 'created_at'];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'usage_date' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }
}
