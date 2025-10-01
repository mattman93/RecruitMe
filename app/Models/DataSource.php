<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'url',
        'fetch_type',
        'data_return_type',
        'headers',
        'custom_data',
        'is_active',
        'rate_limit_per_hour',
        'last_fetched_at',
        'total_jobs_fetched',
        'description',
    ];

    protected $casts = [
        'headers' => 'array',
        'custom_data' => 'array',
        'is_active' => 'boolean',
        'last_fetched_at' => 'datetime',
    ];

    /**
     * Get leads that were fetched from this data source
     */
    public function leads()
    {
        return $this->hasMany(Lead::class, 'source_platform', 'name');
    }

    /**
     * Scope to get only active data sources
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get data sources by fetch type
     */
    public function scopeFetchType($query, $type)
    {
        return $query->where('fetch_type', $type);
    }

    /**
     * Check if the data source is within rate limits
     */
    public function withinRateLimit()
    {
        if (!$this->last_fetched_at) {
            return true;
        }

        $hoursSinceLastFetch = $this->last_fetched_at->diffInHours(now());
        return $hoursSinceLastFetch >= 1; // Simple hourly check
    }

    /**
     * Update fetch statistics
     */
    public function updateFetchStats($jobCount = 0)
    {
        $this->update([
            'last_fetched_at' => now(),
            'total_jobs_fetched' => $this->total_jobs_fetched + $jobCount,
        ]);
    }
}
