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
}
