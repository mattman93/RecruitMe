<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\JobApplication;
use App\Services\JobMatchingService;
use App\Services\JobApplicationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ProcessAutoApply implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting auto-apply job...');

        // Find all Pro users with auto-apply enabled
        $users = User::where('subscription_plan', 'pro')
            ->whereHas('settings', function ($query) {
                $query->where('auto_apply_enabled', true);
            })
            ->with('settings')
            ->get();

        Log::info("Found {$users->count()} users with auto-apply enabled");

        $totalApplicationsQueued = 0;

        foreach ($users as $user) {
            try {
                $applicationsQueued = $this->processUserAutoApply($user);
                $totalApplicationsQueued += $applicationsQueued;
            } catch (\Exception $e) {
                Log::error("Failed to process auto-apply for user {$user->id}: " . $e->getMessage(), [
                    'exception' => $e,
                ]);
            }
        }

        Log::info("Auto-apply job complete. Total applications queued: {$totalApplicationsQueued}");
    }

    /**
     * Process auto-apply for a single user.
     */
    protected function processUserAutoApply(User $user): int
    {
        $settings = $user->settings;

        if (!$settings) {
            Log::warning("User {$user->id} has no settings, skipping");
            return 0;
        }

        // Reset daily count if it's a new day
        $this->resetDailyCountIfNeeded($user);

        // Check if user has hit daily limit
        $maxPerDay = $settings->max_applications_per_day ?? 25;
        if ($user->daily_applications_count >= $maxPerDay) {
            Log::info("User {$user->id} has hit daily limit ({$user->daily_applications_count}/{$maxPerDay})");
            return 0;
        }

        // For hourly frequency, check time window (8am-7pm in user's timezone)
        if ($settings->auto_apply_frequency === 'hourly') {
            $userTimezone = $user->timezone ?? 'America/New_York';
            $currentHour = Carbon::now($userTimezone)->hour;

            if ($currentHour < 8 || $currentHour >= 19) {
                Log::info("User {$user->id} outside active hours (8am-7pm), skipping (current hour: {$currentHour})");
                return 0;
            }
        }

        // Check if user should run based on frequency
        if (!$this->shouldRunForUser($user, $settings->auto_apply_frequency)) {
            Log::info("User {$user->id} not ready for auto-apply yet (frequency: {$settings->auto_apply_frequency})");
            return 0;
        }

        // Use JobMatchingService to find relevant jobs
        $matchingService = new JobMatchingService();
        $relevantJobs = $matchingService->findRelevantJobs($user->id, 100); // Get up to 100 potential matches

        if ($relevantJobs->isEmpty()) {
            Log::info("No relevant jobs found for user {$user->id}");
            $user->update(['last_auto_apply_at' => now()]);
            return 0;
        }

        // Filter by relevance threshold
        $minScore = $this->getMinimumScoreForThreshold($settings->auto_apply_relevance);
        $filteredJobs = $relevantJobs->filter(function ($job) use ($minScore) {
            return $job->relevance_score >= $minScore;
        });

        Log::info("User {$user->id}: Found {$filteredJobs->count()} jobs above threshold (min score: {$minScore})");

        if ($filteredJobs->isEmpty()) {
            Log::info("No jobs above relevance threshold for user {$user->id}");
            $user->update(['last_auto_apply_at' => now()]);
            return 0;
        }

        // Determine how many applications to create based on frequency
        if ($settings->auto_apply_frequency === 'hourly') {
            // For hourly: 2-3 applications per run
            $maxThisRun = rand(2, 3);
        } else {
            // For daily/weekly: use max_per_period setting
            $maxThisRun = $settings->auto_apply_max_per_period ?? 5;
        }

        // Don't exceed daily limit
        $remainingToday = $maxPerDay - $user->daily_applications_count;
        $maxThisRun = min($maxThisRun, $remainingToday);

        Log::info("User {$user->id}: Will apply to max {$maxThisRun} jobs this run (daily: {$user->daily_applications_count}/{$maxPerDay})");

        $jobsToApply = $filteredJobs->sortByDesc('relevance_score')->take($maxThisRun);

        // Create job applications with proper form data
        $applicationsCreated = 0;
        $applicationService = new JobApplicationService();

        foreach ($jobsToApply as $job) {
            try {
                // Check if already applied (double-check to be safe)
                $existingApplication = JobApplication::where('user_id', $user->id)
                    ->where('lead_id', $job->id)
                    ->first();

                if ($existingApplication) {
                    Log::debug("User {$user->id} already has application for lead {$job->id}, skipping");
                    continue;
                }

                // Use JobApplicationService to queue application with proper form data
                $application = $applicationService->queueApplication($user, $job);

                // Update method to full_auto for auto-apply
                $application->update(['application_method' => 'full_auto']);

                $applicationsCreated++;

                // Increment daily counter
                $user->increment('daily_applications_count');

                Log::info("Queued application for user {$user->id} to lead {$job->id} (score: {$job->relevance_score})");
            } catch (\Exception $e) {
                Log::error("Failed to create application for user {$user->id}, lead {$job->id}: " . $e->getMessage());
            }
        }

        // Update last run time
        $user->refresh(); // Refresh to get updated daily count
        $user->update(['last_auto_apply_at' => now()]);

        Log::info("User {$user->id}: Queued {$applicationsCreated} applications");

        return $applicationsCreated;
    }

    /**
     * Check if auto-apply should run for this user based on frequency and last run time.
     */
    protected function shouldRunForUser(User $user, string $frequency): bool
    {
        if (!$user->last_auto_apply_at) {
            return true; // First run
        }

        $lastRun = Carbon::parse($user->last_auto_apply_at);
        $now = Carbon::now();

        switch ($frequency) {
            case 'hourly':
                return $lastRun->diffInMinutes($now) >= 60;
            case 'daily':
                return $lastRun->diffInHours($now) >= 24;
            case 'weekly':
                return $lastRun->diffInDays($now) >= 7;
            default:
                return false;
        }
    }

    /**
     * Reset daily application count if it's a new day.
     */
    protected function resetDailyCountIfNeeded(User $user): void
    {
        if (!$user->last_daily_reset_at) {
            // First time - set reset time to today
            $user->update([
                'daily_applications_count' => 0,
                'last_daily_reset_at' => now()->startOfDay()
            ]);
            return;
        }

        $lastReset = Carbon::parse($user->last_daily_reset_at);
        $now = Carbon::now();

        // If last reset was on a different day, reset the counter
        if (!$lastReset->isToday()) {
            $user->update([
                'daily_applications_count' => 0,
                'last_daily_reset_at' => $now->startOfDay()
            ]);
            Log::info("Reset daily application count for user {$user->id}");
        }
    }

    /**
     * Get minimum relevance score based on user's threshold setting.
     */
    protected function getMinimumScoreForThreshold(string $threshold): int
    {
        switch ($threshold) {
            case 'high':
                return 50; // High quality matches only (80%+)
            case 'medium':
                return 30; // Medium quality matches (60%+)
            case 'broad':
                return 15; // Broader matches (40%+)
            default:
                return 50; // Default to high
        }
    }
}
