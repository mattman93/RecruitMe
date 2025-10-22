<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

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
        'user_role',
        'credits',
        'credits_used',
        'stripe_customer_id',
        'stripe_subscription_id',
        'subscription_status',
        'subscription_plan',
        'subscription_ends_at',
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
        return $this->uploadedFiles()->where('file_type', 'resume')->exists();
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
}
