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

        return response()->json([
            'success' => true,
            'files' => $uploadedFiles
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