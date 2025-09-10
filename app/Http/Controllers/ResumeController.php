<?php

namespace App\Http\Controllers;

use App\Models\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ResumeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

public function upload(Request $request)
{
    $request->validate([
        'files' => 'required|array',
        'files.*' => 'file|mimes:pdf,doc,docx,jpg,jpeg,png,gif,webp|max:10240'
    ]);

    $user = Auth::user();
    $uploadedFiles = [];
    
    foreach ($request->file('files') as $file) {
        $fileName = time() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs("user-uploads/{$user->id}", $fileName);
        
        // Save to database
        $resume = $user->resumes()->create([
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => $fileName,
            'path' => $path,
            'size' => $file->getSize(),
            'type' => $file->getMimeType()
        ]);
        
        $uploadedFiles[] = $resume;
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