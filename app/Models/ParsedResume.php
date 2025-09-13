<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParsedResume extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'full_name',
        'email',
        'phone',
        'linkedin_url',
        'github_url',
        'portfolio_url',
        'location',
        'professional_summary',
        'objective',
        'work_experience',
        'education',
        'technical_skills',
        'soft_skills',
        'languages',
        'certifications',
        'awards',
        'projects',
        'original_filename',
        'file_path',
        'file_type',
        'file_size',
        'raw_text',
        'parsed_data',
        'parsing_confidence',
        'parsing_method',
        'parsed_at',
        'years_of_experience',
        'current_job_title',
        'current_company',
        'is_actively_looking',
        'preferred_locations',
        'preferred_job_types',
        'expected_salary_min',
        'expected_salary_max',
    ];

    protected $casts = [
        'work_experience' => 'array',
        'education' => 'array',
        'technical_skills' => 'array',
        'soft_skills' => 'array',
        'languages' => 'array',
        'certifications' => 'array',
        'awards' => 'array',
        'projects' => 'array',
        'parsed_data' => 'array',
        'preferred_locations' => 'array',
        'preferred_job_types' => 'array',
        'is_actively_looking' => 'boolean',
        'parsing_confidence' => 'float',
        'file_size' => 'integer',
        'years_of_experience' => 'integer',
        'expected_salary_min' => 'decimal:2',
        'expected_salary_max' => 'decimal:2',
        'parsed_at' => 'datetime',
    ];

    protected $dates = [
        'parsed_at',
        'deleted_at',
    ];

    /**
     * Get the user that owns the resume.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the most recent work experience.
     */
    public function getCurrentPosition()
    {
        if (empty($this->work_experience)) {
            return null;
        }

        return collect($this->work_experience)
            ->sortByDesc('start_date')
            ->first(function ($job) {
                return $job['is_current'] ?? false;
            }) ?? collect($this->work_experience)->first();
    }

    /**
     * Calculate total years of experience from work history.
     */
    public function calculateYearsOfExperience(): int
    {
        if (empty($this->work_experience)) {
            return 0;
        }

        $totalMonths = collect($this->work_experience)->reduce(function ($carry, $job) {
            $start = \Carbon\Carbon::parse($job['start_date'] ?? now());
            $end = isset($job['end_date']) && !($job['is_current'] ?? false) 
                ? \Carbon\Carbon::parse($job['end_date']) 
                : now();
            
            return $carry + $start->diffInMonths($end);
        }, 0);

        return intval($totalMonths / 12);
    }

    /**
     * Get all unique skills (technical + soft skills).
     */
    public function getAllSkills(): array
    {
        return array_unique(array_merge(
            $this->technical_skills ?? [],
            $this->soft_skills ?? []
        ));
    }

    /**
     * Check if resume matches job requirements.
     */
    public function matchesJobRequirements(array $requiredSkills, float $threshold = 0.5): bool
    {
        $userSkills = array_map('strtolower', $this->getAllSkills());
        $requiredSkills = array_map('strtolower', $requiredSkills);
        
        $matches = count(array_intersect($userSkills, $requiredSkills));
        $matchPercentage = $matches / count($requiredSkills);
        
        return $matchPercentage >= $threshold;
    }

    /**
     * Get highest education level.
     */
    public function getHighestEducation()
    {
        if (empty($this->education)) {
            return null;
        }

        $educationLevels = [
            'doctorate' => 5,
            'phd' => 5,
            'masters' => 4,
            'master' => 4,
            'bachelors' => 3,
            'bachelor' => 3,
            'associates' => 2,
            'associate' => 2,
            'diploma' => 1,
            'certificate' => 1,
        ];

        return collect($this->education)
            ->sortByDesc(function ($edu) use ($educationLevels) {
                $degree = strtolower($edu['degree'] ?? '');
                foreach ($educationLevels as $level => $rank) {
                    if (str_contains($degree, $level)) {
                        return $rank;
                    }
                }
                return 0;
            })
            ->first();
    }

    /**
     * Format resume data for API response.
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'location' => $this->location,
            'current_position' => $this->getCurrentPosition(),
            'years_of_experience' => $this->years_of_experience ?? $this->calculateYearsOfExperience(),
            'skills' => $this->getAllSkills(),
            'is_actively_looking' => $this->is_actively_looking,
            'expected_salary_range' => $this->expected_salary_min && $this->expected_salary_max
                ? ['min' => $this->expected_salary_min, 'max' => $this->expected_salary_max]
                : null,
            'parsed_at' => $this->parsed_at,
        ];
    }
}