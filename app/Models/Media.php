<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use HasUuid;

    protected $fillable = ['disk', 'path', 'mime_type', 'size', 'metadata'];

    protected $casts = [
        'size' => 'integer',
        'metadata' => 'array',
    ];

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
