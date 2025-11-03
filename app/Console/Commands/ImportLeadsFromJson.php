<?php

namespace App\Console\Commands;

use App\Models\Lead;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportLeadsFromJson extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leads:import {file : Path to JSON file to import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import leads from JSON file (from local environment)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filepath = $this->argument('file');

        if (!file_exists($filepath)) {
            $this->error("File not found: {$filepath}");
            return 1;
        }

        $this->info("Reading file: {$filepath}");

        $data = json_decode(file_get_contents($filepath), true);

        if (!$data || !isset($data['leads'])) {
            $this->error('Invalid JSON file format');
            return 1;
        }

        $leads = $data['leads'];
        $totalCount = count($leads);
        $importedCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;

        $this->info("Found {$totalCount} leads in file (exported at {$data['exported_at']})");

        $progressBar = $this->output->createProgressBar($totalCount);
        $progressBar->start();

        foreach ($leads as $leadData) {
            try {
                // Find existing lead by source_url (unique identifier)
                $existingLead = Lead::where('source_url', $leadData['source_url'])->first();

                if ($existingLead) {
                    // Update existing lead if data has changed
                    $existingLead->update($leadData);
                    $updatedCount++;
                } else {
                    // Create new lead
                    Lead::create($leadData);
                    $importedCount++;
                }
            } catch (\Exception $e) {
                $this->newLine();
                $this->warn("Skipped lead (ID: {$leadData['id']}): " . $e->getMessage());
                $skippedCount++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("Import complete!");
        $this->info("✓ New leads imported: {$importedCount}");
        $this->info("✓ Existing leads updated: {$updatedCount}");

        if ($skippedCount > 0) {
            $this->warn("⚠ Skipped: {$skippedCount}");
        }

        return 0;
    }
}
