<?php

namespace App\Services;

use App\Models\ParsedResume;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use Smalot\PdfParser\Parser as PdfParser;

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
        return $this->saveParsedResume([
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
    }

    /**
     * Store the uploaded file.
     */
    protected function storeFile(UploadedFile $file, int $userId): string
    {
        $fileName = time() . '_' . $userId . '_' . $file->getClientOriginalName();
        $path = $file->storeAs('resumes/' . $userId, $fileName, 'private');
        
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
        // For now, we'll just return a placeholder
        // You can implement PHPWord parsing if needed
        throw new \Exception('Word document parsing not yet implemented. Please upload PDF or TXT files.');
    }

    /**
     * Parse resume text using OpenAI.
     */
    public function parseWithOpenAI(string $resumeText): array
    {
        $prompt = $this->buildParsingPrompt();
        
        try {
            $response = OpenAI::chat()->create([
                'model' => 'gpt-5-nano',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Extract key information from this resume and return as JSON. Be concise.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt . "\n\nResume text:\n" . $resumeText
                    ]
                ],
                // Temperature parameter not supported by gpt-5-nano, uses default of 1
                'max_completion_tokens' => 6000,  // Balanced for gpt-5-nano reasoning + output
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
            
            return $parsed;
            
        } catch (\Exception $e) {
            Log::error('OpenAI parsing failed: ' . $e->getMessage());
            
            // Fallback to basic parsing
            return $this->fallbackParsing($resumeText);
        }
    }

    /**
     * Build the prompt for OpenAI parsing.
     */
    protected function buildParsingPrompt(): string
    {
        return <<<'PROMPT'
Extract basic information from this resume and return JSON with these fields:

{
    "full_name": "Name",
    "email": "email@example.com", 
    "phone": "phone number",
    "location": "city, state",
    "current_job_title": "current position",
    "current_company": "current employer",
    "years_of_experience": 5,
    "technical_skills": ["skill1", "skill2"],
    "work_experience": [{"title": "Job Title", "company": "Company", "start_date": "2022-01", "end_date": "2024-01", "is_current": true}],
    "education": [{"degree": "Bachelor's", "school": "University", "graduation_date": "2020"}],
    "parsing_confidence": 0.9
}

Return only valid JSON. Use null for missing fields.
PROMPT;
    }

    /**
     * Fallback parsing using regex patterns.
     */
    protected function fallbackParsing(string $text): array
    {
        $data = [
            'full_name' => null,
            'email' => null,
            'phone' => null,
            'location' => null,
            'technical_skills' => [],
            'work_experience' => [],
            'education' => [],
            'parsing_confidence' => 0.3,
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
            if (preg_match('/^[A-Z][a-z]+ [A-Z][a-z]+/', $line)) {
                $data['full_name'] = $line;
                break;
            }
        }
        
        return $data;
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
}