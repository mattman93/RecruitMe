<?php

namespace App\Http\Controllers;

use App\Models\UploadedFile;
use App\Services\ResumeParserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ResumeController extends Controller
{
    public function upload(Request $request)
    {
        Log::info('Resume upload endpoint hit', ['user_id' => Auth::id(), 'has_files' => $request->hasFile('files')]);

        $request->validate([
            'files' => 'required|array',
            'files.*' => 'file|mimes:pdf,doc,docx,jpg,jpeg,png,gif,webp|max:10240'
        ]);

        $user = Auth::user();
        $uploadedFiles = [];
        $parserService = new ResumeParserService();

        // Check if this is a replacement (existing resume exists)
        $existingResumes = $user->uploadedFiles()
            ->where('file_type', 'resume')
            ->where('is_active', true)
            ->get();

        $isReplacement = $existingResumes->count() > 0;

        // If it's a replacement, check the rate limit
        if ($isReplacement) {
            // Check if 48 hours have passed since last reset
            if ($user->last_resume_replacement_reset_at) {
                $hoursSinceReset = $user->last_resume_replacement_reset_at->diffInHours(now());

                // If 48 hours have passed, reset the counter
                if ($hoursSinceReset >= 48) {
                    $user->resume_replacement_count = 0;
                    $user->last_resume_replacement_reset_at = now();
                    $user->save();
                }
            }

            // Check if user has exceeded the limit
            if ($user->resume_replacement_count >= 3) {
                $hoursRemaining = 48 - ($user->last_resume_replacement_reset_at ? $user->last_resume_replacement_reset_at->diffInHours(now()) : 0);

                return response()->json([
                    'success' => false,
                    'error' => 'Resume replacement limit reached',
                    'message' => "You've reached the limit of 3 resume replacements. Please wait {$hoursRemaining} hours before replacing your resume again.",
                    'hours_remaining' => $hoursRemaining,
                    'limit_reached' => true
                ], 429);
            }
        }

        foreach ($existingResumes as $existingResume) {
            Log::info('Deleting existing resume', [
                'user_id' => $user->id,
                'file_id' => $existingResume->id,
                'file_path' => $existingResume->file_path
            ]);

            // Delete the file from storage
            if (Storage::exists($existingResume->file_path)) {
                Storage::delete($existingResume->file_path);
            }

            // Delete work experience associated with old resume
            $user->workExperience()->delete();

            // Delete the database record
            $existingResume->delete();
        }

        Log::info('About to process files', ['file_count' => count($request->file('files'))]);

        foreach ($request->file('files') as $file) {
            $fileName = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs("user-uploads/{$user->id}", $fileName);

            // Save to database using uploadedFiles relationship
            $uploadedFile = $user->uploadedFiles()->create([
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'file_type' => 'resume',
                'is_active' => true
            ]);

            // Parse the resume to extract work experience and other data
            try {
                Log::info('Starting resume parsing', ['file_path' => $path, 'user_id' => $user->id]);
                $parsedResume = $parserService->parseResumeFromStorage($path, $user->id);
                Log::info('Resume parsed successfully', ['parsed_resume_id' => $parsedResume->id]);
            } catch (\Exception $e) {
                Log::error('Failed to parse resume', [
                    'error' => $e->getMessage(),
                    'file_path' => $path,
                    'user_id' => $user->id
                ]);
                // Continue even if parsing fails - the file is still uploaded
            }

            $uploadedFiles[] = $uploadedFile;
        }

        // If this was a replacement, increment the counter
        if ($isReplacement) {
            if (!$user->last_resume_replacement_reset_at) {
                $user->last_resume_replacement_reset_at = now();
            }
            $user->resume_replacement_count += 1;
            $user->save();

            Log::info('Resume replacement count incremented', [
                'user_id' => $user->id,
                'count' => $user->resume_replacement_count,
                'reset_at' => $user->last_resume_replacement_reset_at
            ]);
        }

        return response()->json([
            'success' => true,
            'files' => $uploadedFiles,
            'replaced' => $isReplacement,
            'replacements_remaining' => 3 - $user->resume_replacement_count
        ]);
    }

        // In ResumeController.php
    public function processTemporaryUpload(Request $request)
    {
        $request->validate([
            'file_data' => 'required|string',
            'file_name' => 'required|string',
            'file_type' => 'required|string',
            'file_size' => 'required|integer',
        ]);

        // Decode the base64 file data and save it properly
        // This handles the transition from temp to permanent storage
    }

    public function dashboard()
    {
        $resumes = Auth::user()->uploadedFiles()
            ->where('file_type', 'resume')
            ->where('is_active', true)
            ->latest()
            ->get();

        return view('dashboard', compact('resumes'));
    }
}