<?php

namespace App\Models\Connections;

use App\Models\Connections\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatabaseDataSourceConfig extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'data_source_id', 'schema_name', 'table_name', 'primary_key', 'incremental_column', 'allowed_columns', 'excluded_columns', 'filters', 'row_limit', 'read_only', 'raw_sql', 'validated_at'];

    protected function casts(): array
    {
        return [
            'allowed_columns' => 'array',
            'excluded_columns' => 'array',
            'filters' => 'array',
            'read_only' => 'boolean',
            'validated_at' => 'datetime',
        ];
    }

    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class);
    }
}
