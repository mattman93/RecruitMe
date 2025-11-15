<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'avatar',
        'resume_path',
        'parsed_resume_id',
        'active_uploaded_file_id',
        'user_role',
        'credits',
        'credits_used',
        'stripe_customer_id',
        'stripe_subscription_id',
        'subscription_status',
        'subscription_plan',
        'subscription_ends_at',
        'timezone',
        'match_email_frequency',
        'last_match_email_sent_at',
        'match_email_count',
        'last_engagement_at',
        'last_auto_apply_at',
        'daily_applications_count',
        'last_daily_reset_at',
    ];

    // User role constants
    const ROLE_USER = 0;
    const ROLE_STAFF = 1;
    const ROLE_SUPER_ADMIN = 2;

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'subscription_ends_at' => 'datetime',
            'last_match_email_sent_at' => 'datetime',
            'last_engagement_at' => 'datetime',
            'last_auto_apply_at' => 'datetime',
            'last_daily_reset_at' => 'datetime',
            'last_resume_replacement_reset_at' => 'datetime',
        ];
    }

    public function uploadedFiles()
    {
        return $this->hasMany(UploadedFile::class);
    }

    public function workExperience()
    {
        return $this->hasMany(UserWork::class)->orderBy('start_date', 'desc');
    }

    public function parsedResumes()
    {
        return $this->hasMany(ParsedResume::class)->orderBy('parsed_at', 'desc');
    }

    public function activeParsedResume()
    {
        return $this->belongsTo(ParsedResume::class, 'parsed_resume_id');
    }

    public function activeUploadedFile()
    {
        return $this->belongsTo(UploadedFile::class, 'active_uploaded_file_id');
    }

    public function settings()
    {
        return $this->hasOne(UserSettings::class);
    }

    public function oauthTokens()
    {
        return $this->hasMany(UserOAuthToken::class);
    }

    /**
     * Check if user has uploaded a resume
     */
    public function hasResume(): bool
    {
        return !empty($this->resume_path) || $this->uploadedFiles()->where('file_type', 'resume')->where('is_active', true)->exists();
    }

    /**
     * Check if user is a staff admin
     */
    public function isStaff(): bool
    {
        return $this->user_role >= self::ROLE_STAFF;
    }

    /**
     * Check if user is a super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->user_role === self::ROLE_SUPER_ADMIN;
    }

    /**
     * Check if user can access admin features
     */
    public function canAccessAdmin(): bool
    {
        return $this->user_role >= self::ROLE_STAFF;
    }

    /**
     * Check if user has enough credits
     */
    public function hasCredits(int $amount = 1): bool
    {
        return $this->credits >= $amount;
    }

    /**
     * Use credits (deduct from balance)
     */
    public function useCredits(int $amount = 1): bool
    {
        if (!$this->hasCredits($amount)) {
            return false;
        }

        $this->decrement('credits', $amount);
        $this->increment('credits_used', $amount);

        return true;
    }

    /**
     * Add credits to user balance
     */
    public function addCredits(int $amount): void
    {
        $this->increment('credits', $amount);
    }

    /**
     * Get remaining credits
     */
    public function getRemainingCredits(): int
    {
        return $this->credits;
    }

    /**
     * Check if user has an active subscription
     */
    public function hasSubscription(): bool
    {
        return $this->subscription_status === 'active';
    }

    /**
     * Check if subscription is active
     */
    public function isSubscriptionActive(): bool
    {
        return $this->hasSubscription();
    }

    /**
     * Get subscription plan name
     */
    public function getSubscriptionPlan(): ?string
    {
        return $this->subscription_plan;
    }

    /**
     * Check if user has unlimited credits (via active subscription)
     */
    public function hasUnlimitedCredits(): bool
    {
        return $this->hasSubscription();
    }

    /**
     * Check if user should receive a match email based on their preferences and last send time
     */
    public function shouldReceiveMatchEmail(): bool
    {
        // Never send if preference is set to never
        if ($this->match_email_frequency === 'never') {
            return false;
        }

        // If never sent before, send now
        if (!$this->last_match_email_sent_at) {
            return true;
        }

        $hoursSinceLastEmail = $this->last_match_email_sent_at->diffInHours(now());

        // For daily frequency, send if 24+ hours have passed
        if ($this->match_email_frequency === 'daily') {
            return $hoursSinceLastEmail >= 24;
        }

        // For weekly frequency, send if 7 days have passed
        if ($this->match_email_frequency === 'weekly') {
            $daysSinceLastEmail = $this->last_match_email_sent_at->diffInDays(now());
            return $daysSinceLastEmail >= 7;
        }

        return false;
    }

    /**
     * Update last engagement timestamp
     */
    public function updateEngagement(): void
    {
        $this->update(['last_engagement_at' => now()]);
    }

    /**
     * Check if user is actively engaged (within last 7 days)
     */
    public function isActivelyEngaged(): bool
    {
        if (!$this->last_engagement_at) {
            return false;
        }

        return $this->last_engagement_at->diffInDays(now()) <= 7;
    }

    /**
     * Get engagement level for recommended actions
     */
    public function getEngagementLevel(): string
    {
        if (!$this->last_engagement_at) {
            return 'new'; // New user, no engagement yet
        }

        $daysSinceEngagement = $this->last_engagement_at->diffInDays(now());

        if ($daysSinceEngagement <= 1) {
            return 'high'; // Very active
        } elseif ($daysSinceEngagement <= 7) {
            return 'medium'; // Active
        } elseif ($daysSinceEngagement <= 30) {
            return 'low'; // Declining
        } else {
            return 'dormant'; // Inactive
        }
    }

    /**
     * Record that a match email was sent
     */
    public function recordMatchEmailSent(): void
    {
        $this->update([
            'last_match_email_sent_at' => now(),
            'match_email_count' => $this->match_email_count + 1,
        ]);
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }
}
