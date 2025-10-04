<?php

namespace App\Console\Commands;

use App\Models\UploadedFile;
use App\Models\ParsedResume;
use App\Services\ResumeParserService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ParseUnparsedResumes extends Command
{
    protected $signature = 'resumes:parse-unparsed {--user_id= : Parse resumes for a specific user ID}';
    protected $description = 'Parse all uploaded resumes that haven\'t been parsed yet';

    protected $resumeParserService;

    public function __construct(ResumeParserService $resumeParserService)
    {
        parent::__construct();
        $this->resumeParserService = $resumeParserService;
    }

    public function handle()
    {
        $this->info('Finding unparsed resumes...');

        // Get all uploaded resume files
        $query = UploadedFile::where('file_type', 'resume')
            ->where('is_active', true);

        // Filter by user_id if provided
        if ($userId = $this->option('user_id')) {
            $query->where('user_id', $userId);
            $this->info("Filtering for user ID: {$userId}");
        }

        $uploadedResumes = $query->get();

        if ($uploadedResumes->isEmpty()) {
            $this->warn('No uploaded resumes found.');
            return 0;
        }

        $this->info("Found {$uploadedResumes->count()} uploaded resume(s).");

        $parsed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($uploadedResumes as $uploadedFile) {
            // Check if this resume has already been parsed
            $existingParsed = ParsedResume::where('user_id', $uploadedFile->user_id)
                ->where('file_path', $uploadedFile->file_path)
                ->first();

            if ($existingParsed) {
                $this->line("⏭  Skipping (already parsed): {$uploadedFile->original_name} (User: {$uploadedFile->user_id})");
                $skipped++;
                continue;
            }

            try {
                $this->line("🔄 Parsing: {$uploadedFile->original_name} (User: {$uploadedFile->user_id})");

                $this->resumeParserService->parseResumeFromStorage(
                    $uploadedFile->file_path,
                    $uploadedFile->user_id
                );

                $this->info("✅ Successfully parsed: {$uploadedFile->original_name}");
                $parsed++;

            } catch (\Exception $e) {
                $this->error("❌ Failed to parse: {$uploadedFile->original_name}");
                $this->error("   Error: {$e->getMessage()}");
                Log::error("Failed to parse resume", [
                    'file_id' => $uploadedFile->id,
                    'user_id' => $uploadedFile->user_id,
                    'file_path' => $uploadedFile->file_path,
                    'error' => $e->getMessage()
                ]);
                $failed++;
            }
        }

        $this->newLine();
        $this->info("=== Parsing Complete ===");
        $this->info("✅ Parsed: {$parsed}");
        $this->info("⏭  Skipped: {$skipped}");
        $this->info("❌ Failed: {$failed}");

        return 0;
    }
}
