<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
public function check()
{
    $isAuthenticated = Auth::check();
    $user = Auth::user();
    
    \Log::info('Auth check', [
        'authenticated' => $isAuthenticated,
        'user_id' => $user ? $user->id : null,
        'session_id' => session()->getId()
    ]);

    return response()->json([
        'authenticated' => $isAuthenticated,
        'user' => $user
    ]);
}
    public function claimGuestUploads(Request $request)
    {
        $sessionId = session()->getId();
        $user = Auth::user();
        
        // Move files from guest storage to user storage
        $guestPath = "guest-uploads/{$sessionId}";
        $userPath = "user-uploads/{$user->id}";
        
        if (Storage::exists($guestPath)) {
            $files = Storage::files($guestPath);
            foreach ($files as $file) {
                $fileName = basename($file);
                Storage::move($file, "{$userPath}/{$fileName}");
            }
            
            // Clean up guest directory
            Storage::deleteDirectory($guestPath);
            
            return response()->json(['message' => 'Files claimed successfully']);
        }
        
        return response()->json(['message' => 'No files to claim']);
    }
}