<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ApprovedDomain extends Model
{
    protected $fillable = [
        'domain',
        'source',
        'description',
        'is_active',
        'leads_count',
        'last_used_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    /**
     * Check if a domain is approved for proxy access
     */
    public static function isApproved(string $domain): bool
    {
        return self::where('domain', $domain)
                  ->where('is_active', true)
                  ->exists();
    }

    /**
     * Add a new domain for approval
     */
    public static function approve(string $domain, string $source = 'manual', ?string $description = null): self
    {
        return self::updateOrCreate(
            ['domain' => $domain],
            [
                'source' => $source,
                'description' => $description,
                'is_active' => true,
            ]
        );
    }

    /**
     * Update domain usage tracking
     */
    public function recordUsage(): void
    {
        $this->update([
            'last_used_at' => now(),
        ]);
    }

    /**
     * Sync approved domains from existing leads
     */
    public static function syncFromLeads(): int
    {
        $domains = DB::table('leads')
            ->selectRaw('
                SUBSTRING_INDEX(SUBSTRING_INDEX(source_url, "://", -1), "/", 1) as domain,
                COUNT(*) as leads_count
            ')
            ->whereNotNull('source_url')
            ->where('source_url', '!=', '')
            ->where('is_active', true)
            ->groupBy('domain')
            ->get();

        $syncedCount = 0;

        foreach ($domains as $domainData) {
            // Skip invalid domains
            if (empty($domainData->domain) || str_contains($domainData->domain, ' ')) {
                continue;
            }

            $domain = self::updateOrCreate(
                ['domain' => $domainData->domain],
                [
                    'source' => 'lead',
                    'description' => "Auto-approved from {$domainData->leads_count} job leads",
                    'is_active' => true,
                    'leads_count' => $domainData->leads_count,
                ]
            );

            if ($domain->wasRecentlyCreated) {
                $syncedCount++;
            }
        }

        return $syncedCount;
    }

    /**
     * Get domains that should be approved based on current leads
     */
    public static function getDomainsFromLeads(): array
    {
        return DB::table('leads')
            ->selectRaw('
                SUBSTRING_INDEX(SUBSTRING_INDEX(source_url, "://", -1), "/", 1) as domain,
                COUNT(*) as count
            ')
            ->whereNotNull('source_url')
            ->where('source_url', '!=', '')
            ->where('is_active', true)
            ->groupBy('domain')
            ->pluck('count', 'domain')
            ->toArray();
    }
}
