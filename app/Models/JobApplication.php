<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'lead_id', 
        'job_site_structure_id',
        'application_method',
        'status',
        'form_data_sent',
        'custom_responses',
        'automation_log',
        'screenshots',
        'error_message',
        'playwright_session_data',
        'final_application_url',
        'confirmation_number',
        'confirmation_message',
        'automation_duration_seconds',
        'retry_count',
        'queued_at',
        'started_at',
        'completed_at',
        'acknowledgment_received',
        'acknowledgment_received_at',
        'follow_up_emails'
    ];

    protected $casts = [
        'form_data_sent' => 'array',
        'custom_responses' => 'array',
        'screenshots' => 'array',
        'playwright_session_data' => 'array',
        'follow_up_emails' => 'array',
        'acknowledgment_received' => 'boolean',
        'queued_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'acknowledgment_received_at' => 'datetime'
    ];

    /**
     * Get the user that owns the application
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the job lead this application is for
     */
    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Get the site structure used for this application
     */
    public function jobSiteStructure()
    {
        return $this->belongsTo(JobSiteStructure::class);
    }

    /**
     * Scope for queued applications
     */
    public function scopeQueued($query)
    {
        return $query->where('status', 'queued');
    }

    /**
     * Scope for completed applications
     */
    public function scopeCompleted($query)
    {
        return $query->whereIn('status', ['submitted', 'failed']);
    }

    /**
     * Scope for successful applications
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'submitted');
    }

    /**
     * Check if application is in progress
     */
    public function isInProgress(): bool
    {
        return in_array($this->status, ['queued', 'in_progress', 'form_filled']);
    }

    /**
     * Check if application is completed
     */
    public function isCompleted(): bool
    {
        return in_array($this->status, ['submitted', 'failed']);
    }

    /**
     * Get duration in human readable format
     */
    public function getDurationAttribute(): ?string
    {
        if (!$this->automation_duration_seconds) {
            return null;
        }

        $seconds = $this->automation_duration_seconds;
        
        if ($seconds < 60) {
            return "{$seconds}s";
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $remainingSeconds = $seconds % 60;
            return "{$minutes}m {$remainingSeconds}s";
        } else {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return "{$hours}h {$minutes}m";
        }
    }
}