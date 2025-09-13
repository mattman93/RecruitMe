<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ParsedResume;
use App\Services\ResumeParserService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ApiResumeParseController extends Controller
{
    protected $parserService;

    public function __construct(ResumeParserService $parserService)
    {
        $this->parserService = $parserService;
    }

    /**
     * Parse and store a resume.
     * 
     * POST /api/resume/parse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'resume' => 'required|file|mimes:pdf,txt,doc,docx|max:5120', // 5MB max
                'user_id' => 'sometimes|exists:users,id',
            ]);

            $userId = $validated['user_id'] ?? Auth::id();
            
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User authentication required',
                ], 401);
            }

            // Check if user already has a parsed resume
            $existingResume = ParsedResume::where('user_id', $userId)
                ->latest()
                ->first();

            // Parse the resume
            $parsedResume = $this->parserService->parseResume(
                $request->file('resume'),
                $userId
            );

            // If there was an existing resume, soft delete it
            if ($existingResume) {
                $existingResume->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'Resume parsed successfully',
                'data' => $parsedResume->toApiArray(),
                'parsing_confidence' => $parsedResume->parsing_confidence,
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Resume parsing failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to parse resume. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get all parsed resumes for the authenticated user.
     * 
     * GET /api/resume/parse
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user_id ?? Auth::id();
        
        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'User authentication required',
            ], 401);
        }

        $resumes = ParsedResume::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($resume) {
                return $resume->toApiArray();
            });

        return response()->json([
            'success' => true,
            'data' => $resumes,
        ]);
    }

    /**
     * Get a specific parsed resume.
     * 
     * GET /api/resume/parse/{id}
     */
    public function show($id): JsonResponse
    {
        $resume = ParsedResume::findOrFail($id);
        
        // Check authorization
        if ($resume->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $resume->toApiArray(),
                'full_data' => $resume->parsed_data,
                'work_experience' => $resume->work_experience,
                'education' => $resume->education,
                'skills' => [
                    'technical' => $resume->technical_skills,
                    'soft' => $resume->soft_skills,
                ],
                'certifications' => $resume->certifications,
                'projects' => $resume->projects,
            ],
        ]);
    }

    /**
     * Update parsed resume metadata.
     * 
     * PUT /api/resume/parse/{id}
     */
    public function update(Request $request, $id): JsonResponse
    {
        $resume = ParsedResume::findOrFail($id);
        
        // Check authorization
        if ($resume->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $validated = $request->validate([
            'is_actively_looking' => 'sometimes|boolean',
            'preferred_locations' => 'sometimes|array',
            'preferred_job_types' => 'sometimes|array',
            'expected_salary_min' => 'sometimes|numeric|min:0',
            'expected_salary_max' => 'sometimes|numeric|min:0',
            'current_job_title' => 'sometimes|string|max:255',
            'current_company' => 'sometimes|string|max:255',
        ]);

        $resume->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Resume metadata updated successfully',
            'data' => $resume->toApiArray(),
        ]);
    }

    /**
     * Delete a parsed resume.
     * 
     * DELETE /api/resume/parse/{id}
     */
    public function destroy($id): JsonResponse
    {
        $resume = ParsedResume::findOrFail($id);
        
        // Check authorization
        if ($resume->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $resume->delete(); // Soft delete

        return response()->json([
            'success' => true,
            'message' => 'Resume deleted successfully',
        ]);
    }

    /**
     * Match resume against job requirements.
     * 
     * POST /api/resume/parse/{id}/match
     */
    public function matchJob(Request $request, $id): JsonResponse
    {
        $resume = ParsedResume::findOrFail($id);
        
        // Check authorization
        if ($resume->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $validated = $request->validate([
            'required_skills' => 'required|array',
            'threshold' => 'sometimes|numeric|min:0|max:1',
        ]);

        $threshold = $validated['threshold'] ?? 0.5;
        $matches = $resume->matchesJobRequirements(
            $validated['required_skills'],
            $threshold
        );

        $userSkills = $resume->getAllSkills();
        $matchedSkills = array_intersect(
            array_map('strtolower', $userSkills),
            array_map('strtolower', $validated['required_skills'])
        );

        return response()->json([
            'success' => true,
            'data' => [
                'matches' => $matches,
                'match_percentage' => round((count($matchedSkills) / count($validated['required_skills'])) * 100, 2),
                'matched_skills' => array_values($matchedSkills),
                'missing_skills' => array_values(array_diff(
                    array_map('strtolower', $validated['required_skills']),
                    array_map('strtolower', $userSkills)
                )),
                'user_skills' => $userSkills,
            ],
        ]);
    }

    /**
     * Re-parse an existing resume file.
     * 
     * POST /api/resume/parse/{id}/reparse
     */
    public function reparse($id): JsonResponse
    {
        $resume = ParsedResume::findOrFail($id);
        
        // Check authorization
        if ($resume->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        try {
            // Re-parse the raw text with OpenAI
            $parsedData = $this->parserService->parseWithOpenAI($resume->raw_text);
            
            // Update the resume with new parsed data
            $resume->update([
                'parsed_data' => $parsedData,
                'full_name' => $parsedData['full_name'] ?? $resume->full_name,
                'email' => $parsedData['email'] ?? $resume->email,
                'phone' => $parsedData['phone'] ?? $resume->phone,
                'work_experience' => $parsedData['work_experience'] ?? $resume->work_experience,
                'education' => $parsedData['education'] ?? $resume->education,
                'technical_skills' => $parsedData['technical_skills'] ?? $resume->technical_skills,
                'soft_skills' => $parsedData['soft_skills'] ?? $resume->soft_skills,
                'parsed_at' => now(),
                'parsing_confidence' => $parsedData['parsing_confidence'] ?? 0.5,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Resume re-parsed successfully',
                'data' => $resume->toApiArray(),
            ]);

        } catch (\Exception $e) {
            Log::error('Resume re-parsing failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to re-parse resume',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}