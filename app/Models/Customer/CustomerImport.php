<?php

namespace App\Models\Customer;

use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerImport extends Model
{
    use HasPublicUuid;

    protected $fillable = ['resource_type', 'original_filename', 'storage_path', 'status', 'total_rows', 'processed_rows', 'created_rows', 'updated_rows', 'failed_rows', 'failure_report', 'created_by', 'completed_at'];
    protected function casts(): array { return ['failure_report' => 'array', 'completed_at' => 'datetime']; }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
