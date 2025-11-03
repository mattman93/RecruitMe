<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;

class ImportLeadsFromLatestExport implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Only run on production
        if (!app()->environment('production')) {
            Log::info('ImportLeadsFromLatestExport: Skipping - not production environment');
            return;
        }

        Log::info('Starting automatic lead import from latest export...');

        $exportDir = storage_path('app/exports');

        // Create exports directory if it doesn't exist
        if (!file_exists($exportDir)) {
            mkdir($exportDir, 0755, true);
            Log::info('Created exports directory');
        }

        // Get all export files
        $files = glob($exportDir . '/leads_export_*.json');

        if (empty($files)) {
            Log::info('No export files found to import');
            return;
        }

        // Sort by modification time, newest first
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        $latestFile = $files[0];
        $filename = basename($latestFile);
        $fileTime = Carbon::createFromTimestamp(filemtime($latestFile));

        // Check if file is recent (within last 2 hours)
        if ($fileTime->lt(now()->subHours(2))) {
            Log::info("Latest export file is too old ({$fileTime->diffForHumans()}), skipping import", [
                'file' => $filename,
                'file_time' => $fileTime->toIso8601String()
            ]);
            return;
        }

        // Check if we've already imported this file
        $importedMarker = $latestFile . '.imported';
        if (file_exists($importedMarker)) {
            Log::info("File already imported, skipping", [
                'file' => $filename
            ]);
            return;
        }

        Log::info("Importing latest export file: {$filename}", [
            'file_age' => $fileTime->diffForHumans(),
            'file_size' => round(filesize($latestFile) / 1024, 2) . ' KB'
        ]);

        // Import the file
        try {
            Artisan::call('leads:import', [
                'file' => $latestFile
            ]);

            $output = Artisan::output();
            Log::info("Lead import completed", [
                'file' => $filename,
                'output' => trim($output)
            ]);

            // Mark as imported
            file_put_contents($importedMarker, now()->toIso8601String());

        } catch (\Exception $e) {
            Log::error("Failed to import leads", [
                'file' => $filename,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
