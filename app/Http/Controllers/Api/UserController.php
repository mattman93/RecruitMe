<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UploadedFile;
use App\Services\JobApplicationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function getResume(Request $request)
    {
        $user = Auth::user();
        
        // Get the most recent active resume for the user
        $latestResume = $user->uploadedFiles()
            ->where('is_active', true)
            ->where('file_type', 'resume')
            ->latest()
            ->first();
        
        if ($latestResume) {
            $fileInfo = [
                'id' => $latestResume->id,
                'original_name' => $latestResume->original_name,
                'stored_name' => basename($latestResume->file_path),
                'path' => $latestResume->file_path,
                'size' => $latestResume->file_size,
                'type' => $latestResume->mime_type,
                'created_at' => $latestResume->created_at->toISOString()
            ];
            
            return response()->json(['resume' => $fileInfo]);
        }
        
        return response()->json(['resume' => null]);
    }

    public function getAllFiles(Request $request)
    {
        $user = Auth::user();
        
        // Get all active files for the user
        $files = $user->uploadedFiles()
            ->where('is_active', true)
            ->latest()
            ->get();
        
        $formattedFiles = $files->map(function ($file) {
            return [
                'id' => $file->id,
                'original_name' => $file->original_name,
                'stored_name' => basename($file->file_path),
                'path' => $file->file_path,
                'size' => $file->file_size,
                'type' => $file->mime_type,
                'file_type' => $file->file_type,
                'created_at' => $file->created_at->toISOString()
            ];
        });
        
        return response()->json(['files' => $formattedFiles]);
    }

    public function downloadFile(Request $request, $id)
    {
        $user = Auth::user();
        
        // Find the file and verify it belongs to the authenticated user
        $uploadedFile = $user->uploadedFiles()
            ->where('id', $id)
            ->where('is_active', true)
            ->first();
        
        if (!$uploadedFile) {
            return response()->json(['message' => 'File not found'], 404);
        }
        
        // Check if file exists in storage
        if (!Storage::exists($uploadedFile->file_path)) {
            return response()->json(['message' => 'File not found in storage'], 404);
        }
        
        // Return the file with appropriate headers
        return Storage::response($uploadedFile->file_path, $uploadedFile->original_name, [
            'Content-Type' => $uploadedFile->mime_type,
        ]);
    }

    public function getWorkExperience(Request $request)
    {
        $user = $request->user();
        
        $workExperience = $user->workExperience()
            ->orderBy('start_date', 'desc')
            ->get()
            ->map(function ($work) {
                return [
                    'id' => $work->id,
                    'job_title' => $work->job_title,
                    'company' => $work->company,
                    'location' => $work->location,
                    'date_range' => $work->date_range,
                    'description' => $work->description,
                    'achievements' => $work->achievements,
                    'is_current' => $work->is_current,
                ];
            });

        return response()->json([
            'work_experience' => $workExperience,
        ]);
    }

    public function getApplicationFormData(Request $request)
    {
        $user = $request->user();
        $applicationService = new JobApplicationService();
        
        // Create a mock lead for data preparation (we just need user data)
        $mockLead = new \App\Models\Lead();
        
        try {
            $formData = $applicationService->prepareApplicationData($user, $mockLead);
            
            // Transform to frontend format
            $frontendData = [
                'firstName' => $formData['personal']['first_name'] ?? '',
                'lastName' => $formData['personal']['last_name'] ?? '',
                'email' => $formData['personal']['email'] ?? '',
                'phone' => $formData['personal']['phone'] ?? '',
                'linkedinUrl' => $formData['personal']['linkedin_url'] ?? '',
                'portfolioUrl' => $formData['personal']['portfolio_url'] ?? '',
                'currentLocation' => $formData['location']['current_location'] ?? '',
                'address' => $formData['personal']['address'] ?? '',
                'city' => $formData['personal']['city'] ?? '',
                'state' => $formData['personal']['state'] ?? '',
                'zip' => $formData['personal']['zip'] ?? '',
                'experience' => collect($formData['experience'])->map(function ($exp) {
                    return [
                        'company' => $exp['company'] ?? '',
                        'position' => $exp['position'] ?? '',
                        'startDate' => $exp['start_date'] ?? '',
                        'endDate' => $exp['end_date'] ?? '',
                        'isCurrent' => $exp['is_current'] ?? false,
                    ];
                })->toArray()
            ];
            
            return response()->json([
                'success' => true,
                'formData' => $frontendData
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to prepare application data: ' . $e->getMessage()
            ], 500);
        }
    }
}