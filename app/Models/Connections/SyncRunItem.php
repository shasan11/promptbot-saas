<?php

namespace App\Models\Connections;

use App\Models\Connections\Concerns\BelongsToTenant;
use App\Models\Connections\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncRunItem extends Model
{
    use BelongsToTenant, HasUuid;

    protected $fillable = ['tenant_id', 'sync_run_id', 'data_source_id', 'external_id', 'operation', 'status', 'content_hash', 'metadata', 'error_code', 'error_message'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function syncRun(): BelongsTo
    {
        return $this->belongsTo(SyncRun::class);
    }

    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class);
    }
}
