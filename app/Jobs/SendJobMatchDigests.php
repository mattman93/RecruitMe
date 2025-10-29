<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Lead;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class SendJobMatchDigests implements ShouldQueue
{
    use Queueable;

    public $timeout = 600; // 10 minutes
    public $tries = 2;

    private $emailsSent = 0;
    private $emailsFailed = 0;

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
        Log::info('Starting SendJobMatchDigests job');

        // Get all users who might need emails
        $users = User::whereNotNull('email')
            ->where('match_email_frequency', '!=', 'never')
            ->get();

        Log::info("Found {$users->count()} users with email preferences");

        foreach ($users as $user) {
            try {
                $this->processUser($user);
            } catch (\Exception $e) {
                $this->emailsFailed++;
                Log::error("Failed to process user {$user->id}: " . $e->getMessage());
            }
        }

        Log::info("SendJobMatchDigests completed. Sent: {$this->emailsSent}, Failed: {$this->emailsFailed}");
    }

    private function processUser(User $user): void
    {
        // Check if user should receive email based on preferences and timing
        if (!$user->shouldReceiveMatchEmail()) {
            return;
        }

        // Check if it's the right time in the user's timezone (8am)
        if (!$this->isRightTimeForUser($user)) {
            return;
        }

        // Get new matches (leads) since last email
        $cutoffDate = $user->last_match_email_sent_at ?? now()->subWeek();
        $newMatches = Lead::where('created_at', '>=', $cutoffDate)
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        // Don't send if no new matches
        if ($newMatches->isEmpty()) {
            Log::info("No new matches for user {$user->id}, skipping");
            return;
        }

        // Smart frequency adjustment: switch to daily if 100+ matches
        if ($newMatches->count() >= 100 && $user->match_email_frequency === 'weekly') {
            Log::info("User {$user->id} has {$newMatches->count()} matches, temporarily sending as daily");
        }

        // Send the email
        $this->sendDigestEmail($user, $newMatches);

        // Record that email was sent
        $user->recordMatchEmailSent();

        $this->emailsSent++;
        Log::info("Sent match digest to user {$user->id} with {$newMatches->count()} matches");
    }

    private function isRightTimeForUser(User $user): bool
    {
        // Get current time in user's timezone
        $userTime = Carbon::now($user->timezone ?? 'America/New_York');

        // Send emails between 7am and 9am in user's timezone
        $hour = $userTime->hour;
        return $hour >= 7 && $hour < 9;
    }

    private function sendDigestEmail(User $user, $newMatches): void
    {
        $matchCount = $newMatches->count();
        $topMatches = $newMatches->take(10)->map(function ($lead) {
            // Transform leads to match expected format
            return (object) [
                'lead' => $lead,
                'relevance_score' => 85, // Default relevance score for now
            ];
        });

        $engagementLevel = $user->getEngagementLevel();
        $recommendedAction = $this->getRecommendedAction($user, $engagementLevel);

        // Calculate stats
        $stats = $this->calculateMatchStats($newMatches);

        $subject = "{$matchCount} New Job Match" . ($matchCount > 1 ? 'es' : '') . " This " .
                   ($user->match_email_frequency === 'daily' ? 'Day' : 'Week');

        $emailData = [
            'user' => $user,
            'matchCount' => $matchCount,
            'topMatches' => $topMatches,
            'stats' => $stats,
            'engagementLevel' => $engagementLevel,
            'recommendedAction' => $recommendedAction,
            'viewAllUrl' => url('/dashboard'),
        ];

        try {
            Mail::send('emails.job-match-digest', $emailData, function ($message) use ($user, $subject) {
                $message->to($user->email, $user->name)
                        ->subject($subject);
            });
        } catch (\Exception $e) {
            Log::error("Failed to send email to user {$user->id}: " . $e->getMessage());
            throw $e;
        }
    }

    private function calculateMatchStats($matches): array
    {
        $remoteCount = 0;
        $salaries = [];
        $locations = [];

        foreach ($matches as $lead) {
            // Count remote jobs
            if (in_array($lead->workplace_type, ['Remote', 'Hybrid'])) {
                $remoteCount++;
            }

            // Collect salaries
            if ($lead->yearly_min_compensation) {
                $salaries[] = $lead->yearly_min_compensation;
            }

            // Collect locations
            if ($lead->location) {
                $locations[] = $lead->location;
            }
        }

        // Calculate average salary
        $avgSalary = !empty($salaries) ? array_sum($salaries) / count($salaries) : null;

        // Get top 3 locations
        $locationCounts = array_count_values($locations);
        arsort($locationCounts);
        $topLocations = array_slice(array_keys($locationCounts), 0, 3);

        // Calculate remote percentage
        $remotePercentage = $matches->count() > 0 ? round(($remoteCount / $matches->count()) * 100) : 0;

        return [
            'avgSalary' => $avgSalary,
            'topLocations' => $topLocations,
            'remotePercentage' => $remotePercentage,
            'remoteCount' => $remoteCount,
        ];
    }

    private function getRecommendedAction(User $user, string $engagementLevel): string
    {
        switch ($engagementLevel) {
            case 'high':
                return "You're on fire! Keep applying to maintain momentum.";
            case 'medium':
                return "Great progress! Try to apply to at least 5 jobs this week.";
            case 'low':
                return "Don't lose momentum! Set aside 30 minutes today to review these matches.";
            case 'dormant':
                return "Welcome back! Your perfect job might be in this week's matches.";
            case 'new':
            default:
                return "Start strong! Review your top matches and apply to 2-3 today.";
        }
    }
}
