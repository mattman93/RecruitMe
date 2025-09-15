<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobSiteStructure extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain',
        'platform_name',
        'site_pattern',
        'application_flow',
        'form_fields',
        'button_selectors',
        'field_mappings',
        'validation_rules',
        'dynamic_fields',
        'has_captcha',
        'captcha_type',
        'anti_bot_measures',
        'success_indicators',
        'error_selectors',
        'success_rate',
        'total_attempts',
        'notes',
        'automation_strategy',
        'last_structure_update',
        'last_successful_application',
        'is_active'
    ];

    protected $casts = [
        'application_flow' => 'array',
        'form_fields' => 'array',
        'button_selectors' => 'array',
        'field_mappings' => 'array',
        'validation_rules' => 'array',
        'dynamic_fields' => 'array',
        'anti_bot_measures' => 'array',
        'success_indicators' => 'array',
        'error_selectors' => 'array',
        'has_captcha' => 'boolean',
        'is_active' => 'boolean',
        'last_structure_update' => 'datetime',
        'last_successful_application' => 'datetime'
    ];

    /**
     * Get applications that used this site structure
     */
    public function jobApplications()
    {
        return $this->hasMany(JobApplication::class);
    }

    /**
     * Update success rate based on applications
     */
    public function updateSuccessRate(): void
    {
        $total = $this->jobApplications()->count();
        
        if ($total > 0) {
            $successful = $this->jobApplications()
                ->where('status', 'submitted')
                ->count();
            
            $this->update([
                'success_rate' => round(($successful / $total) * 100),
                'total_attempts' => $total
            ]);
        }
    }
}