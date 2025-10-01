<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserWork extends Model
{
    use HasFactory;
    
    protected $table = 'user_work';

    protected $fillable = [
        'user_id',
        'uploaded_file_id',
        'job_title',
        'company',
        'location',
        'start_date',
        'end_date',
        'is_current',
        'description',
        'achievements',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
        'achievements' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function uploadedFile(): BelongsTo
    {
        return $this->belongsTo(UploadedFile::class);
    }
    
    public function getDateRangeAttribute(): string
    {
        $start = $this->start_date ? $this->start_date->format('M Y') : 'Unknown';
        
        if ($this->is_current) {
            return "$start - Present";
        }
        
        $end = $this->end_date ? $this->end_date->format('M Y') : 'Unknown';
        return "$start - $end";
    }
}
