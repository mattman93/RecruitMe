<?php

namespace App\Console\Commands;

use App\Models\ApprovedDomain;
use Illuminate\Console\Command;

class SyncApprovedDomains extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domains:sync {--force : Force sync even if domains already exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync approved domains from existing job leads';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Syncing approved domains from job leads...');
        
        try {
            // Get current approved domains count
            $existingCount = ApprovedDomain::count();
            $this->info("Current approved domains: {$existingCount}");
            
            // Show domains that would be synced from leads
            $domainsFromLeads = ApprovedDomain::getDomainsFromLeads();
            $this->info("Domains found in leads: " . count($domainsFromLeads));
            
            if (empty($domainsFromLeads)) {
                $this->warn('No domains found in active leads');
                return 0;
            }
            
            // Display domains that will be processed
            $this->table(
                ['Domain', 'Lead Count'],
                collect($domainsFromLeads)->map(fn($count, $domain) => [$domain, $count])->toArray()
            );
            
            if (!$this->option('force') && $existingCount > 0) {
                if (!$this->confirm('Some approved domains already exist. Continue with sync?')) {
                    $this->info('Sync cancelled.');
                    return 0;
                }
            }
            
            // Perform the sync
            $syncedCount = ApprovedDomain::syncFromLeads();
            
            $this->info("✅ Successfully synced {$syncedCount} new domains from leads");
            
            // Show final summary
            $finalCount = ApprovedDomain::count();
            $this->info("Total approved domains: {$finalCount}");
            
            // Show recently added domains
            if ($syncedCount > 0) {
                $recentDomains = ApprovedDomain::where('source', 'lead')
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get(['domain', 'leads_count', 'description']);
                
                $this->info("\nRecently approved domains:");
                $this->table(
                    ['Domain', 'Leads', 'Description'],
                    $recentDomains->map(fn($d) => [$d->domain, $d->leads_count, $d->description])->toArray()
                );
            }
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error("Error syncing domains: " . $e->getMessage());
            return 1;
        }
    }
}
