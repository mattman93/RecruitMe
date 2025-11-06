<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class SyncDatabaseToProduction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:sync-to-production {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export local leads table and import to production (OVERWRITES LEADS TABLE ONLY!)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Safety check - only run on local/development
        if (app()->environment('production')) {
            $this->error('❌ This command should only run on local environment, not production!');
            return 1;
        }

        // Get environment variables
        $prodHost = env('PROD_HOST');
        $prodUser = env('PROD_USER');
        $prodPath = env('PROD_PATH');
        $dbName = env('DB_DATABASE', 'recruit_me');
        $dbUser = env('DB_USERNAME', 'laravel');
        $dbPass = env('DB_PASSWORD', 'secret');

        if (!$prodHost || !$prodUser || !$prodPath) {
            $this->error('❌ Production environment variables not configured!');
            $this->error('Please set PROD_HOST, PROD_USER, and PROD_PATH in .env');
            return 1;
        }

        // Confirmation prompt
        if (!$this->option('force')) {
            $this->warn('⚠️  WARNING: This will COMPLETELY OVERWRITE the production LEADS table!');
            $this->warn('⚠️  All leads in production will be REPLACED with local leads!');

            if (!$this->confirm('Are you absolutely sure you want to continue?', false)) {
                $this->info('Cancelled.');
                return 0;
            }

            if (!$this->confirm('Type YES again to confirm', false)) {
                $this->info('Cancelled.');
                return 0;
            }
        }

        $this->info('🚀 Starting leads table sync to production...');

        // Create exports directory if it doesn't exist
        $exportDir = storage_path('app/db-exports');
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $timestamp = now()->format('Y-m-d_His');
        $dumpFile = "{$exportDir}/leads_table_{$timestamp}.sql";

        // Step 1: Dump local leads table only
        $this->info('📦 Exporting local leads table...');

        // Run mysqldump from the database container
        $dumpCommand = sprintf(
            'docker exec recruit-me-db mysqldump -u%s -p%s %s leads > %s',
            escapeshellarg($dbUser),
            escapeshellarg($dbPass),
            escapeshellarg($dbName),
            escapeshellarg($dumpFile)
        );

        $result = Process::run($dumpCommand);

        if (!$result->successful()) {
            $this->error('❌ Failed to export database');
            $this->error($result->errorOutput());
            return 1;
        }

        $fileSize = filesize($dumpFile) / 1024 / 1024; // MB
        $this->info(sprintf('✓ Leads table exported: %.2f MB', $fileSize));

        // Step 2: Compress the dump
        $this->info('🗜️  Compressing database dump...');
        $gzipCommand = sprintf('gzip -f %s', escapeshellarg($dumpFile));
        $result = Process::run($gzipCommand);

        if (!$result->successful()) {
            $this->error('❌ Failed to compress dump');
            return 1;
        }

        $dumpFileGz = "{$dumpFile}.gz";
        $compressedSize = filesize($dumpFileGz) / 1024 / 1024; // MB
        $this->info(sprintf('✓ Compressed to: %.2f MB', $compressedSize));

        // Step 3: Upload to production
        $this->info('📤 Uploading to production (this may take a few minutes)...');

        // Use rsync with progress and partial transfer support
        $rsyncCommand = sprintf(
            'rsync -avz --progress --partial %s %s@%s:/tmp/',
            escapeshellarg($dumpFileGz),
            escapeshellarg($prodUser),
            escapeshellarg($prodHost)
        );

        $result = Process::timeout(600)->run($rsyncCommand);

        if (!$result->successful()) {
            $this->error('❌ Failed to upload to production');
            $this->error($result->errorOutput());
            return 1;
        }

        $this->info('✓ Upload complete');

        // Step 4: Truncate and import on production
        $this->info('📥 Truncating production leads table and importing...');
        $this->warn('This may take several minutes...');

        $remoteFilename = basename($dumpFileGz);

        $importCommand = sprintf(
            'ssh %s@%s "cd %s && docker exec -i recruit-me-db mysql -uroot -proot_secret recruit_me -e \"SET FOREIGN_KEY_CHECKS=0; TRUNCATE TABLE leads; SET FOREIGN_KEY_CHECKS=1;\" && gunzip -c /tmp/%s | docker exec -i recruit-me-db mysql -uroot -proot_secret recruit_me && rm /tmp/%s"',
            escapeshellarg($prodUser),
            escapeshellarg($prodHost),
            escapeshellarg($prodPath),
            escapeshellarg($remoteFilename),
            escapeshellarg($remoteFilename)
        );

        $result = Process::run($importCommand);

        if (!$result->successful()) {
            $this->error('❌ Failed to import on production');
            $this->error($result->errorOutput());
            $this->warn('The dump file is still on production at: /tmp/' . $remoteFilename);
            return 1;
        }

        $this->info('✓ Leads table imported successfully');

        // Clean up local file
        if (file_exists($dumpFileGz)) {
            unlink($dumpFileGz);
            $this->info('✓ Cleaned up local dump file');
        }

        $this->info('');
        $this->info('✅ Leads table sync complete!');
        $this->info('Production leads table is now in sync with local.');

        return 0;
    }
}
