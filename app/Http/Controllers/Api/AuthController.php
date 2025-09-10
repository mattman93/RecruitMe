<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);

            if (Auth::attempt($request->only('email', 'password'))) {
                $request->session()->regenerate();
                
                return response()->json([
                    'message' => 'Login successful',
                    'user' => Auth::user()
                ]);
            }

            return response()->json([
                'message' => 'The provided credentials do not match our records.',
            ], 422);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred during login.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function register(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            event(new Registered($user));
            Auth::login($user);

            return response()->json([
                'message' => 'Registration successful',
                'user' => $user
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred during registration.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out successfully']);
    }

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