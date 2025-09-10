<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_title',
        'company',
        'pay_range',
        'description',
        'location',
        'employment_type',
        'experience_level',
        'source_url',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}