<?php

namespace App\Console\Commands;

use App\Models\Lead;
use Illuminate\Console\Command;
use Carbon\Carbon;

class ExportLeadsToJson extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leads:export {--hours=1 : Hours of leads to export}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export recent leads to JSON file for syncing to production';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hours = $this->option('hours');
        $cutoffTime = Carbon::now()->subHours($hours);

        $this->info("Exporting leads created/updated since {$cutoffTime}...");

        // Get recent leads
        $leads = Lead::where(function($query) use ($cutoffTime) {
            $query->where('created_at', '>=', $cutoffTime)
                  ->orWhere('updated_at', '>=', $cutoffTime);
        })->get();

        $count = $leads->count();

        if ($count === 0) {
            $this->warn('No new leads found in the last ' . $hours . ' hour(s)');
            return 0;
        }

        // Create export directory if it doesn't exist
        $exportDir = storage_path('app/exports');
        if (!file_exists($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        // Export to JSON file with timestamp
        $timestamp = Carbon::now()->format('Y-m-d_His');
        $filename = "leads_export_{$timestamp}.json";
        $filepath = "{$exportDir}/{$filename}";

        file_put_contents($filepath, json_encode([
            'exported_at' => Carbon::now()->toIso8601String(),
            'count' => $count,
            'leads' => $leads->toArray()
        ], JSON_PRETTY_PRINT));

        $this->info("✓ Exported {$count} leads to: {$filepath}");
        $this->info("File size: " . round(filesize($filepath) / 1024, 2) . " KB");

        return 0;
    }
}
