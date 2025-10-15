<?php

namespace App\Services;

use App\Models\User;
use App\Models\ParsedResume;
use App\Models\UserWork;
use App\Models\JobApplication;
use App\Models\UserOAuthToken;

class FlowRankService
{
    /**
     * Calculate FlowRank score for a user (50-300 range)
     */
    public function calculateFlowRank(User $user): int
    {
        $score = 0;

        // Base: Resume uploaded (+50)
        $hasResume = ParsedResume::where('user_id', $user->id)->exists();
        if ($hasResume) {
            $score += 50;
        }

        // Profile Quality (max 125)
        $score += $this->calculateProfileQualityScore($user);

        // Integration (max 75)
        $score += $this->calculateIntegrationScore($user);

        // Activity (max 50)
        $score += $this->calculateActivityScore($user);

        // Premium Features (max 50)
        $score += $this->calculatePremiumScore($user);

        // Cap at 300
        return min($score, 300);
    }

    /**
     * Calculate profile quality score (max 125)
     */
    protected function calculateProfileQualityScore(User $user): int
    {
        $score = 0;

        // Work experience count (+50 for 4+)
        $workExperienceCount = UserWork::where('user_id', $user->id)->count();
        if ($workExperienceCount >= 4) {
            $score += 50;
        }

        // Years of experience (+40 for 5+)
        $parsedResume = ParsedResume::where('user_id', $user->id)->latest()->first();
        if ($parsedResume && ($parsedResume->years_of_experience ?? 0) >= 5) {
            $score += 40;
        }

        // Skills count (+20 for 5+)
        if ($parsedResume && !empty($parsedResume->technical_skills) && count($parsedResume->technical_skills) >= 5) {
            $score += 20;
        }

        // Education filled (+15)
        if ($parsedResume && !empty($parsedResume->education)) {
            $score += 15;
        }

        return $score;
    }

    /**
     * Calculate integration score (max 75)
     */
    protected function calculateIntegrationScore(User $user): int
    {
        $score = 0;

        // Gmail OAuth (+50)
        $hasGmailOAuth = UserOAuthToken::where('user_id', $user->id)
            ->where('provider', 'google')
            ->where('is_active', true)
            ->exists();

        if ($hasGmailOAuth) {
            $score += 50;
        }

        // Phone number (+15)
        if (!empty($user->phone)) {
            $score += 15;
        }

        // Preferences set (+10)
        // Assuming user has a preferences JSON column or related model
        // For now, checking if email is verified as a proxy
        if ($user->email_verified_at) {
            $score += 10;
        }

        return $score;
    }

    /**
     * Calculate activity score (max 50)
     */
    protected function calculateActivityScore(User $user): int
    {
        $score = 0;

        $applicationCount = JobApplication::where('user_id', $user->id)->count();

        // Progressive bonuses
        if ($applicationCount >= 1) {
            $score += 15;
        }
        if ($applicationCount >= 5) {
            $score += 15;
        }
        if ($applicationCount >= 10) {
            $score += 20;
        }

        return $score;
    }

    /**
     * Calculate premium features score (max 50)
     */
    protected function calculatePremiumScore(User $user): int
    {
        $score = 0;

        // Auto-apply enabled (+25)
        // Assuming user has an auto_apply_enabled field
        if ($user->auto_apply_enabled ?? false) {
            $score += 25;
        }

        // Premium subscription active (+25)
        // Assuming user has a subscription status field
        if ($user->is_premium ?? false) {
            $score += 25;
        }

        return $score;
    }

    /**
     * Get breakdown of FlowRank score for display
     */
    public function getFlowRankBreakdown(User $user): array
    {
        $hasResume = ParsedResume::where('user_id', $user->id)->exists();
        $workExperienceCount = UserWork::where('user_id', $user->id)->count();
        $parsedResume = ParsedResume::where('user_id', $user->id)->latest()->first();
        $applicationCount = JobApplication::where('user_id', $user->id)->count();
        $hasGmailOAuth = UserOAuthToken::where('user_id', $user->id)
            ->where('provider', 'google')
            ->where('is_active', true)
            ->exists();

        return [
            'total_score' => $this->calculateFlowRank($user),
            'breakdown' => [
                'resume_uploaded' => [
                    'earned' => $hasResume ? 50 : 0,
                    'max' => 50,
                    'completed' => $hasResume
                ],
                'profile_quality' => [
                    'earned' => $this->calculateProfileQualityScore($user),
                    'max' => 125,
                    'details' => [
                        'work_experiences' => $workExperienceCount,
                        'years_experience' => $parsedResume->years_of_experience ?? 0,
                        'skills_count' => $parsedResume && !empty($parsedResume->technical_skills) ? count($parsedResume->technical_skills) : 0,
                        'has_education' => $parsedResume && !empty($parsedResume->education)
                    ]
                ],
                'integration' => [
                    'earned' => $this->calculateIntegrationScore($user),
                    'max' => 75,
                    'details' => [
                        'gmail_oauth' => $hasGmailOAuth,
                        'phone_number' => !empty($user->phone),
                        'email_verified' => !empty($user->email_verified_at)
                    ]
                ],
                'activity' => [
                    'earned' => $this->calculateActivityScore($user),
                    'max' => 50,
                    'details' => [
                        'applications_count' => $applicationCount
                    ]
                ],
                'premium' => [
                    'earned' => $this->calculatePremiumScore($user),
                    'max' => 50,
                    'details' => [
                        'auto_apply_enabled' => $user->auto_apply_enabled ?? false,
                        'is_premium' => $user->is_premium ?? false
                    ]
                ]
            ],
            'max_possible' => 300
        ];
    }
}
