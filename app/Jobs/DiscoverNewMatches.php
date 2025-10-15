<?php

namespace App\Jobs;

use App\Mail\NewJobMatchesNotification;
use App\Models\User;
use App\Models\UserSettings;
use App\Services\JobMatchingService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DiscoverNewMatches implements ShouldQueue
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
    public function handle(JobMatchingService $jobMatchingService): void
    {
        Log::info('DiscoverNewMatches job started');

        // Get all users with new match notifications enabled
        $users = User::whereHas('settings', function ($query) {
            $query->where('notify_new_matches', true);
        })->with('settings')->get();

        Log::info("Found {$users->count()} users with notifications enabled");

        foreach ($users as $user) {
            try {
                $this->processUserNotifications($user, $jobMatchingService);
            } catch (\Exception $e) {
                Log::error("Failed to process notifications for user {$user->id}: {$e->getMessage()}");
            }
        }

        Log::info('DiscoverNewMatches job completed');
    }

    /**
     * Process notifications for a single user
     */
    protected function processUserNotifications(User $user, JobMatchingService $jobMatchingService): void
    {
        $settings = $user->settings;

        // Check if we should send a notification based on digest frequency
        if (!$this->shouldSendNotification($settings)) {
            Log::info("Skipping user {$user->id} - not time for notification yet");
            return;
        }

        // Find relevant jobs for the user
        $relevantJobs = $jobMatchingService->findRelevantJobs($user->id, 50);

        // Filter for jobs created in last 48 hours
        $newJobs = $relevantJobs->filter(function ($job) {
            return Carbon::parse($job->created_at)->greaterThan(Carbon::now()->subHours(48));
        });

        Log::info("User {$user->id}: Found {$newJobs->count()} new matches in last 48 hours");

        // Only send email if there are 5 or more new matches
        if ($newJobs->count() >= 5) {
            Mail::to($user->email)->send(new NewJobMatchesNotification($user, $newJobs));

            // Update last notification timestamp
            $settings->last_match_notification_sent_at = now();
            $settings->save();

            Log::info("Sent new match notification to user {$user->id} with {$newJobs->count()} jobs");
        }
    }

    /**
     * Check if we should send a notification based on digest frequency
     */
    protected function shouldSendNotification(UserSettings $settings): bool
    {
        if (!$settings->last_match_notification_sent_at) {
            return true; // Never sent before
        }

        $lastSent = Carbon::parse($settings->last_match_notification_sent_at);

        switch ($settings->email_digest_frequency) {
            case 'immediate':
                // Allow notification every hour (since job runs hourly)
                return $lastSent->lessThan(Carbon::now()->subHour());

            case 'daily':
                // Allow notification once per day
                return $lastSent->lessThan(Carbon::now()->subDay());

            case 'weekly':
                // Allow notification once per week
                return $lastSent->lessThan(Carbon::now()->subWeek());

            default:
                return true;
        }
    }
}
