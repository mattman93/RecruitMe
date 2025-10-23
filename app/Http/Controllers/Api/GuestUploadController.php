<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ResumeParserService;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class GuestUploadController extends Controller
{
    protected $resumeParser;

    public function __construct(ResumeParserService $resumeParser)
    {
        $this->resumeParser = $resumeParser;
    }

    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'files' => 'required|array',
            'files.*' => 'file|mimes:pdf,doc,docx,jpg,jpeg,png,gif,webp|max:10240'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $sessionId = session()->getId();
        $uploadedFiles = [];
        $parsedData = null;
        $matchedJobs = [];

        foreach ($request->file('files') as $file) {
            $fileName = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs("guest-uploads/{$sessionId}", $fileName);

            $uploadedFiles[] = [
                'original_name' => $file->getClientOriginalName(),
                'stored_name' => $fileName,
                'path' => $path,
                'size' => $file->getSize(),
                'type' => $file->getMimeType()
            ];

            // Parse the first resume file (PDF or DOCX)
            if (!$parsedData && in_array($file->getClientOriginalExtension(), ['pdf', 'doc', 'docx'])) {
                try {
                    Log::info('Parsing guest resume', ['path' => $path, 'session_id' => $sessionId]);
                    $parsedData = $this->parseGuestResume($path);

                    if (!$parsedData) {
                        Log::warning('Guest resume parsing returned null', [
                            'path' => $path,
                            'extension' => $file->getClientOriginalExtension()
                        ]);
                    }

                    // Find matching jobs
                    if ($parsedData) {
                        $matchedJobs = $this->findMatchingJobsForGuest($parsedData);
                        Log::info('Found matching jobs for guest', ['count' => count($matchedJobs)]);
                    } else {
                        Log::warning('No parsed data available for job matching');
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to parse guest resume: ' . $e->getMessage(), [
                        'path' => $path,
                        'exception' => $e->getTraceAsString()
                    ]);
                    // Continue even if parsing fails - we still store the file
                }
            }
        }

        // Store data in session for later account creation
        session([
            'guest_uploads' => $uploadedFiles,
            'guest_parsed_resume' => $parsedData,
            'guest_matched_jobs' => $matchedJobs,
        ]);

        return response()->json([
            'success' => true,
            'files' => $uploadedFiles,
            'session_id' => $sessionId,
            'parsed_data' => $parsedData ? [
                'name' => $parsedData['full_name'] ?? null,
                'email' => $parsedData['email'] ?? null,
                'skills_count' => count($parsedData['technical_skills'] ?? []),
                'experience_years' => $parsedData['years_of_experience'] ?? 0,
            ] : null,
            'preview_matches' => array_slice($matchedJobs, 0, 5), // Return first 5 matches as preview
            'total_matches' => count($matchedJobs),
        ]);
    }

    /**
     * Parse guest resume without creating user account (uses default parsing, not OpenAI)
     */
    protected function parseGuestResume(string $storagePath): ?array
    {
        if (!Storage::exists($storagePath)) {
            Log::error('Guest resume file not found', ['path' => $storagePath]);
            return null;
        }

        $fullPath = Storage::path($storagePath);
        $extension = pathinfo($storagePath, PATHINFO_EXTENSION);

        Log::info('Extracting text from guest resume', [
            'full_path' => $fullPath,
            'extension' => $extension,
            'file_exists' => file_exists($fullPath)
        ]);

        // Extract text from the stored file
        $rawText = $this->extractTextFromFile($fullPath, $extension);

        if (!$rawText) {
            Log::warning('No text extracted from guest resume', [
                'path' => $storagePath,
                'extension' => $extension
            ]);
            return null;
        }

        Log::info('Text extracted successfully, length: ' . strlen($rawText));

        // Use default parsing (keyword extraction) for guests - saves OpenAI costs
        try {
            $parsed = $this->parseResumeWithDefaultMethod($rawText);
            Log::info('Default parsing completed for guest', [
                'has_skills' => !empty($parsed['technical_skills'] ?? []),
                'skills_count' => count($parsed['technical_skills'] ?? [])
            ]);
            return $parsed;
        } catch (\Exception $e) {
            Log::error('Default parsing failed for guest resume: ' . $e->getMessage(), [
                'text_length' => strlen($rawText),
                'exception' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Extract text from uploaded file
     */
    protected function extractTextFromFile(string $fullPath, string $extension): ?string
    {
        $extension = strtolower($extension);

        switch ($extension) {
            case 'pdf':
                return $this->extractTextFromPdf($fullPath);
            case 'txt':
                return file_get_contents($fullPath);
            case 'doc':
            case 'docx':
                // For now, not supported for guests
                return null;
            default:
                return null;
        }
    }

    /**
     * Extract text from PDF using smalot/pdfparser with fallback to pdftotext
     */
    protected function extractTextFromPdf(string $fullPath): ?string
    {
        // Try smalot/pdfparser first
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($fullPath);
            $text = $pdf->getText();

            // Clean up the text
            $text = preg_replace('/\s+/', ' ', $text);
            $text = trim($text);

            if ($text && strlen($text) > 50) {
                Log::info('PDF parsed successfully with smalot/pdfparser');
                return $text;
            }
        } catch (\Exception $e) {
            Log::warning('smalot/pdfparser failed, trying fallback: ' . $e->getMessage());
        }

        // Fallback: Try pdftotext command line tool
        try {
            $output = shell_exec("pdftotext " . escapeshellarg($fullPath) . " - 2>&1");

            if ($output && !str_contains($output, 'command not found') && !str_contains($output, 'not recognized')) {
                $text = preg_replace('/\s+/', ' ', $output);
                $text = trim($text);

                if (strlen($text) > 50) {
                    Log::info('PDF parsed successfully using pdftotext fallback', ['text_length' => strlen($text)]);
                    return $text;
                }
            }
        } catch (\Exception $e) {
            Log::warning('pdftotext fallback also failed: ' . $e->getMessage());
        }

        Log::error('All PDF parsing methods failed for guest upload');
        return null;
    }

    /**
     * Parse resume using default method (keyword extraction, no OpenAI)
     * This is used for guest users to save on API costs
     */
    protected function parseResumeWithDefaultMethod(string $rawText): array
    {
        $text = strtolower($rawText);

        // Extract name - look for first two capitalized words at the beginning
        $name = null;
        if (preg_match('/^([A-Z][a-z]+\s+[A-Z][a-z]+)/', $rawText, $nameMatches)) {
            $name = trim($nameMatches[1]);
        }

        // Extract email
        preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $rawText, $emailMatches);
        $email = $emailMatches[0] ?? null;

        // Extract years of experience (look for patterns like "5 years", "5+ years")
        $yearsOfExperience = 0;
        if (preg_match('/(\d+)\+?\s*years?\s*(of\s*)?(experience|exp)/i', $rawText, $matches)) {
            $yearsOfExperience = (int) $matches[1];
        }

        // Extract technical skills using common keywords
        $commonSkills = [
            // Programming Languages
            'javascript', 'python', 'java', 'php', 'ruby', 'go', 'rust', 'c++', 'c#', 'typescript',
            'swift', 'kotlin', 'scala', 'perl', 'bash', 'powershell', 'sql', 'html', 'css',

            // Frameworks & Libraries
            'react', 'vue', 'angular', 'node', 'express', 'django', 'flask', 'laravel', 'spring',
            'rails', 'asp.net', 'jquery', 'bootstrap', 'tailwind', 'next.js', 'nuxt', 'svelte',

            // Databases
            'mysql', 'postgresql', 'mongodb', 'redis', 'elasticsearch', 'dynamodb', 'cassandra',
            'oracle', 'sql server', 'sqlite', 'mariadb',

            // Cloud & DevOps
            'aws', 'azure', 'gcp', 'docker', 'kubernetes', 'jenkins', 'terraform', 'ansible',
            'git', 'github', 'gitlab', 'ci/cd', 'circleci', 'travis', 'nginx', 'apache',

            // Data & ML
            'tensorflow', 'pytorch', 'pandas', 'numpy', 'scikit-learn', 'spark', 'hadoop',
            'tableau', 'power bi', 'excel',

            // Other
            'agile', 'scrum', 'jira', 'rest', 'graphql', 'api', 'microservices', 'linux',
            'testing', 'unit testing', 'tdd', 'oauth', 'jwt', 'websockets'
        ];

        $foundSkills = [];
        foreach ($commonSkills as $skill) {
            if (strpos($text, $skill) !== false) {
                $foundSkills[] = $skill;
            }
        }

        // Extract current job title (look after keywords like "currently", "current role", or at the top)
        $currentJobTitle = '';
        if (preg_match('/(?:currently|current.*?(?:role|position))[\s:]+([^\n.]{5,50})/i', $rawText, $matches)) {
            $currentJobTitle = trim($matches[1]);
        }

        return [
            'raw_text' => $rawText,
            'full_name' => $name,
            'email' => $email,
            'technical_skills' => array_unique($foundSkills),
            'years_of_experience' => $yearsOfExperience,
            'current_job_title' => $currentJobTitle,
            'parsing_method' => 'default',
        ];
    }

    /**
     * Find matching jobs for guest user based on parsed resume
     */
    protected function findMatchingJobsForGuest(array $parsedData): array
    {
        try {
            $userSkills = array_map('strtolower', $parsedData['technical_skills'] ?? []);
            $experienceYears = $parsedData['years_of_experience'] ?? 0;
            $currentTitle = strtolower($parsedData['current_job_title'] ?? '');

            // Determine experience level
            $experienceLevel = $this->determineExperienceLevel($experienceYears);

            // Get total count first (for showing real potential)
            $totalCount = Lead::where('is_active', true)
                ->whereIn('experience_level', [
                    $experienceLevel,
                    ...$this->getAdjacentLevels($experienceLevel)
                ])
                ->count();

            Log::info('Total active jobs in experience range', ['count' => $totalCount]);

            // Process a sample (500 most recent) for performance
            $jobs = Lead::where('is_active', true)
                ->whereIn('experience_level', [
                    $experienceLevel,
                    ...$this->getAdjacentLevels($experienceLevel)
                ])
                ->orderBy('created_at', 'desc')
                ->limit(500)
                ->get();

            // Score and filter jobs
            $scoredJobs = [];
            foreach ($jobs as $job) {
                $score = $this->calculateJobScore($job, $userSkills, $currentTitle);

                if ($score >= 15) {
                    $scoredJobs[] = [
                        'id' => $job->id,
                        'job_title' => $job->job_title,
                        'company' => $job->company_name,
                        'location' => $job->location,
                        'workplace_type' => $job->workplace_type,
                        'description' => substr($job->description, 0, 200) . '...', // Limited description
                        'score' => $score,
                        'matched_skills' => $this->getMatchingSkills($job, $userSkills),
                        'posted_date' => $job->created_at?->format('Y-m-d'),
                    ];
                }
            }

            // Sort by score descending
            usort($scoredJobs, function($a, $b) {
                return $b['score'] <=> $a['score'];
            });

            Log::info('Found matching jobs for guest', ['count' => count($scoredJobs)]);

            return $scoredJobs; // Return all scored matches
        } catch (\Exception $e) {
            Log::error('Failed to find matching jobs for guest: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Calculate job relevance score for guest matching
     */
    protected function calculateJobScore($job, array $userSkills, string $currentTitle): float
    {
        $score = 0;
        $jobText = strtolower($job->description . ' ' . ($job->requirements_summary ?? ''));

        // Skill matching
        foreach ($userSkills as $skill) {
            if (strpos($jobText, $skill) !== false) {
                $score += 3.5;
            }
        }

        // Title matching
        $jobTitle = strtolower($job->job_title);
        if ($currentTitle && (strpos($jobTitle, $currentTitle) !== false || strpos($currentTitle, $jobTitle) !== false)) {
            $score += 25;
        }

        return $score;
    }

    /**
     * Get matching skills between job and user
     */
    protected function getMatchingSkills($job, array $userSkills): array
    {
        $jobText = strtolower($job->description . ' ' . ($job->requirements_summary ?? ''));
        $matches = [];

        foreach ($userSkills as $skill) {
            if (strpos($jobText, $skill) !== false) {
                $matches[] = $skill;
            }
        }

        return array_slice($matches, 0, 5); // Return top 5 matching skills
    }

    /**
     * Determine experience level from years
     */
    protected function determineExperienceLevel(int $years): string
    {
        if ($years < 2) return 'Entry Level';
        if ($years < 5) return 'Mid Level';
        if ($years < 8) return 'Senior Level';
        return 'Executive Level';
    }

    /**
     * Get adjacent experience levels
     */
    protected function getAdjacentLevels(string $level): array
    {
        $levels = ['Entry Level', 'Mid Level', 'Senior Level', 'Executive Level'];
        $index = array_search($level, $levels);

        return [
            $levels[$index - 1] ?? $level,
            $levels[$index + 1] ?? $level,
        ];
    }

    public function preview($sessionId)
    {
        $files = Storage::files("guest-uploads/{$sessionId}");
        
        return response()->json([
            'files' => $files,
            'session_uploads' => session('guest_uploads', [])
        ]);
    }
}