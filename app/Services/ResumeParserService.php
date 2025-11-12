<?php

namespace App\Services;

use App\Models\ParsedResume;
use App\Models\UserWork;
use App\Models\UploadedFile as UploadedFileModel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use Smalot\PdfParser\Parser as PdfParser;
use PhpOffice\PhpWord\IOFactory;

class ResumeParserService
{
    protected $pdfParser;
    
    public function __construct()
    {
        $this->pdfParser = new PdfParser();
    }

    /**
     * Parse a resume file and store the results.
     */
    public function parseResume(UploadedFile $file, int $userId): ParsedResume
    {
        // Store the original file
        $filePath = $this->storeFile($file, $userId);

        // Extract text from the file
        $rawText = $this->extractText($file);

        // Parse with OpenAI
        $parsedData = $this->parseWithOpenAI($rawText);

        // Create and save the parsed resume
        $parsedResume = $this->saveParsedResume([
            'user_id' => $userId,
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $filePath,
            'file_type' => $file->getClientOriginalExtension(),
            'file_size' => $file->getSize(),
            'raw_text' => $rawText,
            'parsed_data' => $parsedData,
            'parsing_method' => 'openai',
            'parsed_at' => now(),
        ], $parsedData);

        // Save work experience to separate table
        $this->saveWorkExperience($userId, $parsedData['work_experience'] ?? [], $parsedResume);

        return $parsedResume;
    }

    /**
     * Parse a resume from an already stored file path.
     */
    public function parseResumeFromStorage(string $storagePath, int $userId, ?ParsedResume $existingResume = null): ParsedResume
    {
        if (!Storage::exists($storagePath)) {
            throw new \Exception("File not found: {$storagePath}");
        }

        $fullPath = Storage::path($storagePath);
        $extension = pathinfo($storagePath, PATHINFO_EXTENSION);
        $fileName = basename($storagePath);

        // Extract text from the stored file
        $rawText = $this->extractTextFromStoredFile($fullPath, $extension);

        // Parse with OpenAI
        $parsedData = $this->parseWithOpenAI($rawText);

        // Update existing resume or create new one
        if ($existingResume) {
            $existingResume->update([
                'raw_text' => $rawText,
                'parsed_data' => $parsedData,
                'parsing_method' => 'openai',
                'parsed_at' => now(),
                'full_name' => $parsedData['full_name'] ?? $existingResume->full_name,
                'email' => $parsedData['email'] ?? $existingResume->email,
                'phone' => $parsedData['phone'] ?? $existingResume->phone,
                'linkedin_url' => $parsedData['linkedin_url'] ?? $existingResume->linkedin_url,
                'github_url' => $parsedData['github_url'] ?? $existingResume->github_url,
                'portfolio_url' => $parsedData['portfolio_url'] ?? $existingResume->portfolio_url,
                'location' => $parsedData['location'] ?? $existingResume->location,
                'professional_summary' => $parsedData['professional_summary'] ?? $existingResume->professional_summary,
                'objective' => $parsedData['objective'] ?? $existingResume->objective,
                'work_experience' => $parsedData['work_experience'] ?? [],
                'education' => $parsedData['education'] ?? [],
                'technical_skills' => $parsedData['technical_skills'] ?? [],
                'soft_skills' => $parsedData['soft_skills'] ?? [],
                'languages' => $parsedData['languages'] ?? [],
                'certifications' => $parsedData['certifications'] ?? [],
                'awards' => $parsedData['awards'] ?? [],
                'projects' => $parsedData['projects'] ?? [],
                'years_of_experience' => $parsedData['years_of_experience'] ?? $existingResume->years_of_experience,
                'current_job_title' => $parsedData['current_job_title'] ?? $existingResume->current_job_title,
                'current_company' => $parsedData['current_company'] ?? $existingResume->current_company,
                'parsing_confidence' => $parsedData['parsing_confidence'] ?? 0.5,
            ]);
            $parsedResume = $existingResume;
        } else {
            // Create and save the parsed resume
            $parsedResume = $this->saveParsedResume([
                'user_id' => $userId,
                'original_filename' => $fileName,
                'file_path' => $storagePath,
                'file_type' => $extension,
                'file_size' => Storage::size($storagePath),
                'raw_text' => $rawText,
                'parsed_data' => $parsedData,
                'parsing_method' => 'openai',
                'parsed_at' => now(),
            ], $parsedData);
        }

        // Delete old work experience and save new ones
        if ($existingResume) {
            // Find the uploaded file by matching file path
            $uploadedFile = UploadedFileModel::where('user_id', $userId)
                ->where('file_path', $storagePath)
                ->first();

            if ($uploadedFile) {
                UserWork::where('user_id', $userId)
                    ->where('uploaded_file_id', $uploadedFile->id)
                    ->delete();
            }
        }

        // Save work experience to separate table
        $this->saveWorkExperience($userId, $parsedData['work_experience'] ?? [], $parsedResume);

        return $parsedResume;
    }

    /**
     * Extract text from a stored file.
     */
    protected function extractTextFromStoredFile(string $fullPath, string $extension): string
    {
        $extension = strtolower($extension);

        switch ($extension) {
            case 'pdf':
                return $this->extractTextFromStoredPdf($fullPath);
            case 'txt':
                return file_get_contents($fullPath);
            case 'doc':
            case 'docx':
                return $this->extractTextFromStoredWord($fullPath);
            default:
                throw new \Exception("Unsupported file type: {$extension}");
        }
    }

    /**
     * Extract text from a stored PDF file.
     */
    protected function extractTextFromStoredPdf(string $fullPath): string
    {
        try {
            Log::info('Attempting to parse PDF with smalot/pdfparser', ['path' => $fullPath, 'exists' => file_exists($fullPath)]);

            $pdf = $this->pdfParser->parseFile($fullPath);
            $text = $pdf->getText();

            // Clean up the text
            $text = preg_replace('/\s+/', ' ', $text);
            $text = trim($text);

            Log::info('PDF parsing successful', ['text_length' => strlen($text)]);

            return $text;
        } catch (\Exception $e) {
            Log::warning('smalot/pdfparser failed, trying fallback method', [
                'error' => $e->getMessage(),
                'path' => $fullPath
            ]);

            // Fallback: Try to extract text using shell_exec with pdftotext if available
            try {
                $output = shell_exec("pdftotext " . escapeshellarg($fullPath) . " - 2>&1");
                if ($output && !str_contains($output, 'command not found') && !str_contains($output, 'not recognized')) {
                    $text = preg_replace('/\s+/', ' ', $output);
                    $text = trim($text);
                    Log::info('PDF parsing successful using pdftotext fallback', ['text_length' => strlen($text)]);
                    return $text;
                }
            } catch (\Exception $fallbackError) {
                Log::warning('pdftotext fallback also failed', ['error' => $fallbackError->getMessage()]);
            }

            // If both methods fail, return a placeholder text indicating manual parsing is needed
            Log::error('All PDF parsing methods failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'path' => $fullPath
            ]);

            // Return a simple message instead of throwing an exception
            // This allows the upload to succeed even if parsing fails
            return "PDF parsing failed. Please manually add your work experience to your profile.";
        }
    }

    /**
     * Extract text from a stored Word document.
     */
    protected function extractTextFromStoredWord(string $fullPath): string
    {
        try {
            Log::info('Attempting to parse Word document with PhpWord', ['path' => $fullPath, 'exists' => file_exists($fullPath)]);

            $phpWord = IOFactory::load($fullPath);
            $text = '';

            foreach ($phpWord->getSections() as $section) {
                $elements = $section->getElements();
                foreach ($elements as $element) {
                    if (method_exists($element, 'getText')) {
                        $text .= $element->getText() . "\n";
                    } elseif (method_exists($element, 'getElements')) {
                        // Handle containers like TextRun
                        foreach ($element->getElements() as $childElement) {
                            if (method_exists($childElement, 'getText')) {
                                $text .= $childElement->getText();
                            }
                        }
                        $text .= "\n";
                    }
                }
            }

            $text = trim($text);
            Log::info('Word document parsing successful', ['text_length' => strlen($text)]);

            return $text;
        } catch (\Exception $e) {
            Log::error('Failed to parse Word document', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'path' => $fullPath
            ]);

            // Return a simple message instead of throwing an exception
            return "Word document parsing failed. Please manually add your work experience to your profile.";
        }
    }

    /**
     * Store the uploaded file.
     */
    protected function storeFile(UploadedFile $file, int $userId): string
    {
        $fileName = time() . '_' . $userId . '_' . $file->getClientOriginalName();
        $path = $file->storeAs('resumes/' . $userId, $fileName, 'local');
        
        return $path;
    }

    /**
     * Extract text from various file types.
     */
    protected function extractText(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        
        switch ($extension) {
            case 'pdf':
                return $this->extractTextFromPdf($file);
            case 'txt':
                return $file->get();
            case 'doc':
            case 'docx':
                return $this->extractTextFromWord($file);
            default:
                throw new \Exception("Unsupported file type: {$extension}");
        }
    }

    /**
     * Extract text from PDF file.
     */
    protected function extractTextFromPdf(UploadedFile $file): string
    {
        try {
            $pdf = $this->pdfParser->parseFile($file->getPathname());
            $text = $pdf->getText();
            
            // Clean up the text
            $text = preg_replace('/\s+/', ' ', $text);
            $text = trim($text);
            
            return $text;
        } catch (\Exception $e) {
            Log::error('PDF parsing failed: ' . $e->getMessage());
            throw new \Exception('Failed to parse PDF file');
        }
    }

    /**
     * Extract text from Word document.
     */
    protected function extractTextFromWord(UploadedFile $file): string
    {
        try {
            $phpWord = IOFactory::load($file->getRealPath());
            $text = '';

            foreach ($phpWord->getSections() as $section) {
                $elements = $section->getElements();
                foreach ($elements as $element) {
                    if (method_exists($element, 'getText')) {
                        $text .= $element->getText() . "\n";
                    } elseif (method_exists($element, 'getElements')) {
                        // Handle containers like TextRun
                        foreach ($element->getElements() as $childElement) {
                            if (method_exists($childElement, 'getText')) {
                                $text .= $childElement->getText();
                            }
                        }
                        $text .= "\n";
                    }
                }
            }

            return trim($text);
        } catch (\Exception $e) {
            Log::error('Failed to parse Word document', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName()
            ]);
            throw new \Exception('Failed to parse Word document: ' . $e->getMessage());
        }
    }

    /**
     * Parse resume text using OpenAI with retry logic.
     */
    public function parseWithOpenAI(string $resumeText): array
    {
        $prompt = $this->buildParsingPrompt();
        $maxRetries = 3;
        $retryDelay = 1; // seconds

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                Log::info("OpenAI parsing attempt {$attempt}/{$maxRetries}");

                $response = OpenAI::chat()->create([
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Extract key information from this resume and return as JSON. Be concise and accurate.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt . "\n\nResume text:\n" . $resumeText
                        ]
                    ],
                    'temperature' => 0.1,
                    'max_tokens' => 4000,
                    'response_format' => ['type' => 'json_object'],
                ]);

                // Debug: Log the full response structure
                Log::info('OpenAI Full Response: ' . json_encode($response->toArray()));

                $content = $response->choices[0]->message->content;

                // Debug: Log the raw response
                Log::info('OpenAI Raw Content: ' . var_export($content, true));

                $parsed = json_decode($content, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::error('JSON decode error: ' . json_last_error_msg());
                    Log::error('Raw content: ' . $content);
                    throw new \Exception('Invalid JSON response from OpenAI: ' . json_last_error_msg());
                }

                Log::info('OpenAI parsing successful');
                return $parsed;

            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
                Log::warning("OpenAI parsing attempt {$attempt} failed: " . $errorMessage);

                // Check if it's a rate limit or timeout error - retry both
                $isRetryable = str_contains($errorMessage, 'rate limit') ||
                               str_contains($errorMessage, 'Rate limit') ||
                               str_contains($errorMessage, 'timed out') ||
                               str_contains($errorMessage, 'timeout') ||
                               str_contains($errorMessage, 'Operation timed out');

                if ($isRetryable && $attempt < $maxRetries) {
                    $delay = $retryDelay * pow(2, $attempt - 1); // Exponential backoff: 1s, 2s, 4s
                    Log::info("Retryable error detected, waiting {$delay} seconds before retry {$attempt}/{$maxRetries}");
                    sleep($delay);
                    continue;
                }

                // For non-retryable errors or if we've exhausted retries, fall back
                if ($attempt >= $maxRetries) {
                    Log::error('OpenAI parsing failed after all retry attempts, using fallback parser');
                } else {
                    Log::error('Non-retryable OpenAI error, using fallback parser');
                }
                return $this->fallbackParsing($resumeText);
            }
        }

        // If we get here, all retries failed
        Log::error('OpenAI parsing failed after all retry attempts, using fallback parser');
        return $this->fallbackParsing($resumeText);
    }

    /**
     * Build the prompt for OpenAI parsing.
     */
    protected function buildParsingPrompt(): string
    {
        return <<<'PROMPT'
Extract detailed information from this resume and return JSON with these fields:

{
    "full_name": "Name",
    "email": "email@example.com", 
    "phone": "phone number",
    "location": "city, state",
    "current_job_title": "current position",
    "current_company": "current employer",
    "years_of_experience": 5,
    "technical_skills": ["skill1", "skill2"],
    "work_experience": [
        {
            "title": "Job Title", 
            "company": "Company", 
            "location": "City, State",
            "start_date": "2022-01", 
            "end_date": "2024-01", 
            "is_current": true,
            "description": "Brief overview of the role and responsibilities",
            "achievements": [
                "Specific achievement or bullet point",
                "Another achievement or responsibility",
                "Key accomplishment with metrics"
            ]
        }
    ],
    "education": [{"degree": "Bachelor's", "school": "University", "graduation_date": "2020"}],
    "parsing_confidence": 0.9
}

Extract ALL bullet points, achievements, and detailed descriptions for each job. Include specific metrics, technologies used, and accomplishments mentioned. Return only valid JSON. Use null for missing fields.
PROMPT;
    }

    /**
     * Fallback parsing using regex patterns.
     */
    protected function fallbackParsing(string $text): array
    {
        Log::info('Using fallback parsing (regex-based)');

        $data = [
            'full_name' => null,
            'email' => null,
            'phone' => null,
            'location' => null,
            'technical_skills' => [],
            'work_experience' => [],
            'education' => [],
            'parsing_confidence' => 0.6, // Higher confidence for improved fallback
        ];

        // Extract email
        if (preg_match('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', $text, $matches)) {
            $data['email'] = $matches[0];
        }

        // Extract phone
        if (preg_match('/(\+?\d{1,3}[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}/', $text, $matches)) {
            $data['phone'] = $matches[0];
        }

        // Extract LinkedIn URL
        if (preg_match('/linkedin\.com\/in\/[\w-]+/i', $text, $matches)) {
            $data['linkedin_url'] = 'https://' . $matches[0];
        }

        // Extract GitHub URL
        if (preg_match('/github\.com\/[\w-]+/i', $text, $matches)) {
            $data['github_url'] = 'https://' . $matches[0];
        }

        // Try to extract name (usually at the beginning)
        $lines = explode("\n", $text);
        foreach (array_slice($lines, 0, 5) as $line) {
            $line = trim($line);
            // Extract just the name pattern (First Last), stop at first special character
            if (preg_match('/^([A-Z][a-z]+(?:\s+[A-Z][a-z]+)+)/', $line, $matches)) {
                $data['full_name'] = trim($matches[1]);
                break;
            }
        }

        // If full_name is still null or too long, truncate text for safety
        if (!$data['full_name'] || strlen($data['full_name']) > 200) {
            $data['full_name'] = substr($text, 0, 100); // First 100 chars as fallback
        }

        // Extract work experience using pattern matching
        $data['work_experience'] = $this->extractWorkExperienceFromText($text);

        // Extract technical skills
        $data['technical_skills'] = $this->extractSkillsFromText($text);

        // Extract education
        $data['education'] = $this->extractEducationFromText($text);

        Log::info('Fallback parsing complete', [
            'work_experience_count' => count($data['work_experience']),
            'skills_count' => count($data['technical_skills']),
            'education_count' => count($data['education'])
        ]);

        return $data;
    }

    /**
     * Extract work experience from resume text using patterns.
     */
    protected function extractWorkExperienceFromText(string $text): array
    {
        $experiences = [];

        // First, isolate the Work Experience section
        if (preg_match('/Work Experience\s+(.+?)(?:Education|GitHub|Portfolio|$)/is', $text, $sectionMatch)) {
            $workSection = $sectionMatch[1];

            // Pattern for: "Software Engineer, 11/2020 to Current"
            // Followed by: "Company Name – Location"
            // Job titles typically are: Software Engineer, Senior Developer, etc.
            preg_match_all(
                '/(Software Engineer|Senior Developer|Developer|Engineer|Architect|Manager|Lead|Director|Analyst|Specialist|Consultant),\s+(\d{2}\/\d{4})\s+to\s+(Current|\d{2}\/\d{4})\s+([A-Z][a-zA-Z\s\(\)\.]+?)(?:\s*(?:–|-)\s*([A-Za-z\s,]+?))?(?:\s*•)/m',
                $workSection,
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as $match) {
                $title = trim($match[1]);
                $startDate = $match[2];
                $endDate = $match[3];
                $company = trim($match[4]);
                $location = isset($match[5]) ? trim($match[5]) : null;

                $experiences[] = [
                    'title' => $title,
                    'company' => $company,
                    'location' => $location,
                    'start_date' => $startDate,
                    'end_date' => $endDate === 'Current' ? null : $endDate,
                    'is_current' => $endDate === 'Current',
                    'description' => null,
                    'achievements' => []
                ];
            }
        }

        Log::info('Work experience extraction', [
            'pattern_matches' => count($experiences),
            'experiences_extracted' => count($experiences)
        ]);

        return $experiences;
    }

    /**
     * Extract technical skills from resume text.
     */
    protected function extractSkillsFromText(string $text): array
    {
        $skills = [];

        // Look for skills section and extract comma-separated items
        if (preg_match('/(?:Skills|Technical Skills|Technologies)[:\s]+(.+?)(?:\n\n|Work Experience|Education)/is', $text, $match)) {
            $skillsText = $match[1];

            // Extract items after bullet points or commas
            preg_match_all('/(?:•|\*|-|,)\s*([A-Za-z][A-Za-z0-9\s.+#-]+)/', $skillsText, $skillMatches);

            foreach ($skillMatches[1] as $skill) {
                $skill = trim($skill);
                if (strlen($skill) > 2 && strlen($skill) < 50) {
                    $skills[] = $skill;
                }
            }
        }

        return array_unique($skills);
    }

    /**
     * Extract education from resume text.
     */
    protected function extractEducationFromText(string $text): array
    {
        $education = [];

        // Pattern: Degree: Field, Date
        // University - Location
        if (preg_match_all(
            '/(Bachelor|Master|Associate|PhD|Doctor)[^,\n]+:\s+([^,\n]+),\s+(\d{2}\/\d{4})\s+([^\n]+)/i',
            $text,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $education[] = [
                    'degree' => trim($match[1] . ' of Science'),
                    'field_of_study' => trim($match[2]),
                    'school' => trim($match[4]),
                    'graduation_date' => $match[3],
                ];
            }
        }

        return $education;
    }

    /**
     * Save the parsed resume to database.
     */
    protected function saveParsedResume(array $fileData, array $parsedData): ParsedResume
    {
        $resumeData = array_merge($fileData, [
            'full_name' => $parsedData['full_name'] ?? null,
            'email' => $parsedData['email'] ?? null,
            'phone' => $parsedData['phone'] ?? null,
            'linkedin_url' => $parsedData['linkedin_url'] ?? null,
            'github_url' => $parsedData['github_url'] ?? null,
            'portfolio_url' => $parsedData['portfolio_url'] ?? null,
            'location' => $parsedData['location'] ?? null,
            'professional_summary' => $parsedData['professional_summary'] ?? null,
            'objective' => $parsedData['objective'] ?? null,
            'work_experience' => $parsedData['work_experience'] ?? [],
            'education' => $parsedData['education'] ?? [],
            'technical_skills' => $parsedData['technical_skills'] ?? [],
            'soft_skills' => $parsedData['soft_skills'] ?? [],
            'languages' => $parsedData['languages'] ?? [],
            'certifications' => $parsedData['certifications'] ?? [],
            'awards' => $parsedData['awards'] ?? [],
            'projects' => $parsedData['projects'] ?? [],
            'years_of_experience' => $parsedData['years_of_experience'] ?? null,
            'current_job_title' => $parsedData['current_job_title'] ?? null,
            'current_company' => $parsedData['current_company'] ?? null,
            'parsing_confidence' => $parsedData['parsing_confidence'] ?? 0.5,
        ]);

        return ParsedResume::create($resumeData);
    }

    /**
     * Save parsed resume from pre-parsed guest data.
     */
    public function saveParsedResumeFromGuestData(int $userId, string $filePath, array $fileInfo, array $guestParsedData): ParsedResume
    {
        $fileData = [
            'user_id' => $userId,
            'original_filename' => $fileInfo['original_name'],
            'file_path' => $filePath,
            'file_type' => pathinfo($fileInfo['original_name'], PATHINFO_EXTENSION),
            'file_size' => $fileInfo['size'],
            'raw_text' => $guestParsedData['raw_text'] ?? null,
            'parsed_data' => $guestParsedData,
            'parsing_method' => 'openai',
            'parsed_at' => now(),
        ];

        // Create and save the parsed resume
        $parsedResume = $this->saveParsedResume($fileData, $guestParsedData);

        // Save work experience to separate table
        $this->saveWorkExperience($userId, $guestParsedData['work_experience'] ?? [], $parsedResume);

        return $parsedResume;
    }

    /**
     * Save work experience to user_work table.
     */
    protected function saveWorkExperience(int $userId, array $workExperience, ParsedResume $parsedResume): void
    {
        if (empty($workExperience)) {
            return;
        }

        // Find the corresponding UploadedFile record
        $uploadedFile = UploadedFileModel::where('user_id', $userId)
            ->where('file_path', $parsedResume->file_path)
            ->first();

        foreach ($workExperience as $work) {
            UserWork::create([
                'user_id' => $userId,
                'uploaded_file_id' => $uploadedFile?->id,
                'job_title' => $work['title'] ?? 'Unknown Position',
                'company' => $work['company'] ?? 'Unknown Company',
                'location' => $work['location'] ?? null,
                'start_date' => $this->parseDate($work['start_date'] ?? null),
                'end_date' => $this->parseDate($work['end_date'] ?? null),
                'is_current' => $work['is_current'] ?? false,
                'description' => $work['description'] ?? null,
                'achievements' => $work['achievements'] ?? null,
            ]);
        }
    }

    /**
     * Parse date string to proper format.
     */
    protected function parseDate(?string $dateString): ?string
    {
        if (!$dateString || $dateString === 'null' || $dateString === 'Present') {
            return null;
        }

        try {
            // Try parsing various date formats
            $formats = ['Y-m-d', 'Y-m', 'Y', 'M Y', 'F Y', 'm/Y', 'Y/m'];
            
            foreach ($formats as $format) {
                $date = \DateTime::createFromFormat($format, $dateString);
                if ($date !== false) {
                    return $date->format('Y-m-d');
                }
            }

            // If no format matches, try strtotime
            $timestamp = strtotime($dateString);
            if ($timestamp !== false) {
                return date('Y-m-d', $timestamp);
            }
        } catch (\Exception $e) {
            Log::warning("Could not parse date: $dateString", ['error' => $e->getMessage()]);
        }

        return null;
    }
}