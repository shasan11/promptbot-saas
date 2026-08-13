<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspacePurchaseRequest extends Model
{
    use CentralConnection, HasUuid;

    protected $guarded = [];
    protected $casts = ['request_snapshot' => 'array'];

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function account(): BelongsTo { return $this->belongsTo(CustomerAccount::class, 'customer_account_id'); }
    public function portalUser(): BelongsTo { return $this->belongsTo(PortalUser::class); }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
