<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BetaUserMailingList extends Model
{
    protected $table = 'beta_users_mailing_list';

    protected $fillable = [
        'email',
        'name',
        'organization',
        'ip_address',
        'user_agent',
        'additional_data',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'additional_data' => 'array',
    ];
}
