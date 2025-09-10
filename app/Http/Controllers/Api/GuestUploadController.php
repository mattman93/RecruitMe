<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class GuestUploadController extends Controller
{
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
        }

        // Store file metadata in session
        session(['guest_uploads' => $uploadedFiles]);

        return response()->json([
            'success' => true,
            'files' => $uploadedFiles,
            'session_id' => $sessionId
        ]);
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