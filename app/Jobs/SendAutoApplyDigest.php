<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\JobApplication;
use App\Mail\AutoApplyDigest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SendAutoApplyDigest implements ShouldQueue
{
    use Queueable;

    protected string $frequency;

    /**
     * Create a new job instance.
     */
    public function __construct(string $frequency = 'daily')
    {
        $this->frequency = $frequency;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Starting auto-apply digest job ({$this->frequency})...");

        // Find users with auto-apply enabled and matching notification frequency
        $users = User::where('subscription_plan', 'pro')
            ->whereHas('settings', function ($query) {
                $query->where('auto_apply_enabled', true)
                    ->where('notification_frequency', $this->frequency);
            })
            ->with('settings')
            ->get();

        Log::info("Found {$users->count()} users for {$this->frequency} digest");

        $emailsSent = 0;

        foreach ($users as $user) {
            try {
                $applications = $this->getApplicationsForPeriod($user);

                if ($applications->isEmpty()) {
                    Log::info("No applications to report for user {$user->id}");
                    continue;
                }

                // Send digest email
                Mail::to($user->email)->send(new AutoApplyDigest($user, $applications, $this->frequency));
                $emailsSent++;

                Log::info("Sent {$this->frequency} digest to user {$user->id} ({$applications->count()} applications)");
            } catch (\Exception $e) {
                Log::error("Failed to send digest to user {$user->id}: " . $e->getMessage());
            }
        }

        Log::info("Auto-apply digest complete. Emails sent: {$emailsSent}");
    }

    /**
     * Get applications for the relevant time period
     */
    protected function getApplicationsForPeriod(User $user)
    {
        $query = JobApplication::where('user_id', $user->id)
            ->where('application_method', 'full_auto')
            ->with('lead');

        switch ($this->frequency) {
            case 'daily':
                // Applications from today
                $query->whereDate('created_at', Carbon::today());
                break;
            case 'weekly':
                // Applications from this week (Monday to Sunday)
                $query->whereBetween('created_at', [
                    Carbon::now()->startOfWeek(),
                    Carbon::now()->endOfWeek()
                ]);
                break;
        }

        return $query->orderBy('created_at', 'desc')->get();
    }
}
