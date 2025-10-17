<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BetaAccessToken extends Model
{
    protected $fillable = [
        'email',
        'token',
        'activated_at',
        'user_id',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function generateToken(string $email): self
    {
        return self::create([
            'email' => $email,
            'token' => Str::random(32),
        ]);
    }

    public function activate(?int $userId = null): bool
    {
        $this->activated_at = now();
        $this->user_id = $userId;
        return $this->save();
    }

    public function isActivated(): bool
    {
        return !is_null($this->activated_at);
    }
}
