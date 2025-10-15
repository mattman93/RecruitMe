<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSettings extends Model
{
    protected $fillable = [
        'user_id',
        'notify_new_matches',
        'notify_application_updates',
        'email_digest_frequency',
        'min_salary',
        'max_salary',
        'preferred_location',
        'preferred_job_title',
        'employment_types',
        'work_arrangement',
        'willing_to_relocate',
        'queue_auto_apply',
        'autonomous_auto_apply',
        'max_applications_per_day',
        'show_to_recruiters',
        'hide_from_current_employer',
    ];

    protected $casts = [
        'notify_new_matches' => 'boolean',
        'notify_application_updates' => 'boolean',
        'employment_types' => 'array',
        'work_arrangement' => 'array',
        'willing_to_relocate' => 'boolean',
        'queue_auto_apply' => 'boolean',
        'autonomous_auto_apply' => 'boolean',
        'show_to_recruiters' => 'boolean',
        'hide_from_current_employer' => 'boolean',
        'min_salary' => 'integer',
        'max_salary' => 'integer',
        'max_applications_per_day' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
