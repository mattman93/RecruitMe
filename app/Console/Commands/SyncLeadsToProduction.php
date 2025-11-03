<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class SyncLeadsToProduction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leads:sync-to-production {--hours=1 : Hours of leads to sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export leads from local and sync to production server';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Only run on local/development, never on production
        if (app()->environment('production')) {
            $this->error('This command should only run on local environment, not production!');
            return 1;
        }

        $hours = $this->option('hours');

        $this->info("Starting lead sync to production (last {$hours} hour(s))...");

        // Export leads locally
        $this->info('📦 Exporting leads from local...');
        $this->call('leads:export', ['--hours' => $hours]);

        // Get the most recent export file
        $exportDir = storage_path('app/exports');
        $files = glob($exportDir . '/leads_export_*.json');

        if (empty($files)) {
            $this->error('❌ No export file found!');
            return 1;
        }

        rsort($files); // Sort by newest first
        $exportFile = $files[0];
        $filename = basename($exportFile);

        $this->info("✓ Exported to: {$filename}");

        // Prepare environment variables for sync script
        $prodHost = env('PROD_HOST');
        $prodUser = env('PROD_USER');
        $prodPath = env('PROD_PATH');

        if (!$prodHost || !$prodUser || !$prodPath) {
            $this->error('❌ Production environment variables not configured!');
            $this->error('Please set PROD_HOST, PROD_USER, and PROD_PATH in .env');
            return 1;
        }

        // Upload file to production
        $this->info('📤 Uploading to production...');

        $scpCommand = sprintf(
            'scp %s %s@%s:%s/storage/app/exports/',
            escapeshellarg($exportFile),
            escapeshellarg($prodUser),
            escapeshellarg($prodHost),
            escapeshellarg($prodPath)
        );

        $result = Process::run($scpCommand);

        if (!$result->successful()) {
            $this->error('❌ Failed to upload file to production');
            $this->error($result->errorOutput());
            return 1;
        }

        $this->info('✓ File uploaded successfully');
        $this->info('✅ Sync complete! Production will auto-import on next job run.');

        return 0;
    }
}
