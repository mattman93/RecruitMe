<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function getResume(Request $request)
    {
        $user = Auth::user();
        
        // Assuming you have a Resume model or store resume info in user table
        // For now, we'll return the most recent resume from the user's uploads
        $resumePath = "user-uploads/{$user->id}";
        $files = \Storage::files($resumePath);
        
        if (!empty($files)) {
            $latestFile = end($files);
            $fileInfo = [
                'id' => 1,
                'original_name' => basename($latestFile),
                'stored_name' => basename($latestFile),
                'path' => $latestFile,
                'size' => \Storage::size($latestFile),
                'type' => \Storage::mimeType($latestFile),
                'created_at' => now()->toISOString()
            ];
            
            return response()->json(['resume' => $fileInfo]);
        }
        
        return response()->json(['resume' => null]);
    }
}