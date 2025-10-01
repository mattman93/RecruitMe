<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscoveredContact extends Model
{
    protected $fillable = [
        'lead_id',
        'company_name',
        'company_domain',
        'email',
        'contact_type',
        'is_verified',
        'use_count',
        'last_used_at'
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'use_count' => 'integer',
        'last_used_at' => 'datetime'
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Get cached contacts for a lead/domain
     */
    public static function getCachedContacts(Lead $lead, string $domain): array
    {
        return self::where('lead_id', $lead->id)
            ->orWhere('company_domain', $domain)
            ->orderBy('use_count', 'desc')
            ->orderBy('contact_type')
            ->get()
            ->map(function ($contact) {
                return [
                    'email' => $contact->email,
                    'type' => $contact->contact_type
                ];
            })
            ->toArray();
    }

    /**
     * Store discovered contacts for a lead
     */
    public static function storeContacts(Lead $lead, string $domain, string $companyName, array $contacts): void
    {
        foreach ($contacts as $contact) {
            self::updateOrCreate(
                [
                    'lead_id' => $lead->id,
                    'email' => $contact['email']
                ],
                [
                    'company_name' => $companyName,
                    'company_domain' => $domain,
                    'contact_type' => $contact['type'] ?? 'recruiting',
                    'use_count' => 1,
                    'last_used_at' => now()
                ]
            );
        }
    }

    /**
     * Increment use count when contact is used
     */
    public function markAsUsed(): void
    {
        $this->increment('use_count');
        $this->update(['last_used_at' => now()]);
    }
}
