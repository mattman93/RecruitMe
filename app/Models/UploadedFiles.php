<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UploadedFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'original_name',
        'file_path',
        'file_type',
        'mime_type',
        'file_size',
        'metadata',
        'is_active',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getFullPathAttribute(): string
    {
        return storage_path('app/' . $this->file_path);
    }

    public function getUrlAttribute(): string
    {
        return asset('storage/' . str_replace('public/', '', $this->file_path));
    }
}