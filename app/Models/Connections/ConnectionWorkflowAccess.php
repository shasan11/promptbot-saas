<?php

namespace App\Models\Connections;

use App\Models\Connections\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectionWorkflowAccess extends Model
{
    use BelongsToTenant;

    protected $table = 'connection_workflow_access';

    protected $fillable = ['tenant_id', 'connection_id', 'workflow_key', 'allowed_actions', 'allowed_triggers', 'approval_required'];

    protected function casts(): array
    {
        return [
            'allowed_actions' => 'array',
            'allowed_triggers' => 'array',
            'approval_required' => 'boolean',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }
}
