<?php

namespace App\Models\Customer;

use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphedByMany;

class Tag extends Model
{
    use HasPublicUuid;

    protected $fillable = ['name', 'slug', 'description', 'color', 'status', 'created_by'];
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function contacts(): MorphedByMany { return $this->morphedByMany(Contact::class, 'taggable'); }
    public function companies(): MorphedByMany { return $this->morphedByMany(Company::class, 'taggable'); }
}
