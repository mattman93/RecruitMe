<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UploadedFile;
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
}