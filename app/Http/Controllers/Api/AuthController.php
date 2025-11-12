<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UploadedFile;
use App\Services\ResumeParserService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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
                'subscription_plan' => 'pro',
                'subscription_status' => 'active',
                'subscription_ends_at' => now()->addMonth(),
            ]);

            event(new Registered($user));
            Auth::login($user);

            // Automatically claim any guest uploads from the current session
            $claimedFiles = $this->transferGuestDataToUser($user);

            return response()->json([
                'message' => 'Registration successful',
                'user' => $user,
                'claimed_files' => $claimedFiles
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

    /**
     * Transfer guest session data to newly registered user
     */
    protected function transferGuestDataToUser(User $user): array
    {
        $movedFiles = [];
        $userPath = "resumes/{$user->id}";
        $resumeParserService = app(ResumeParserService::class);

        // Get guest uploads and parsed data from session
        $guestUploads = session('guest_uploads', []);
        $guestParsedResume = session('guest_parsed_resume');
        $sessionId = session()->getId();

        if (!empty($guestUploads)) {
            foreach ($guestUploads as $uploadInfo) {
                if (Storage::exists($uploadInfo['path'])) {
                    $fileName = time() . '_' . $user->id . '_' . $uploadInfo['original_name'];
                    $newPath = $userPath . '/' . $fileName;
                    Storage::move($uploadInfo['path'], $newPath);

                    // Create database record
                    $uploadedFile = UploadedFile::create([
                        'user_id' => $user->id,
                        'original_name' => $uploadInfo['original_name'],
                        'file_path' => $newPath,
                        'file_type' => 'resume',
                        'mime_type' => $uploadInfo['type'],
                        'file_size' => $uploadInfo['size'],
                        'is_active' => true,
                    ]);

                    $movedFiles[] = $uploadedFile;

                    // If we have pre-parsed resume data from guest session, save it first (default parsing)
                    // Then re-parse with OpenAI for full analysis
                    if ($guestParsedResume && in_array($uploadInfo['type'], ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])) {
                        try {
                            Log::info("Saving default-parsed resume data for new user {$user->id}");

                            // First, save the default-parsed data from guest session
                            $parsedResume = $resumeParserService->saveParsedResumeFromGuestData(
                                $user->id,
                                $newPath,
                                $uploadInfo,
                                $guestParsedResume
                            );

                            Log::info("Successfully saved default-parsed resume for user {$user->id}", [
                                'parsed_resume_id' => $parsedResume->id,
                                'parsing_method' => 'default'
                            ]);

                            // Now upgrade to OpenAI parsing for full analysis
                            try {
                                Log::info("Upgrading to OpenAI parsing for user {$user->id}");
                                $resumeParserService->parseResumeFromStorage($newPath, $user->id, $parsedResume);
                                Log::info("Successfully upgraded resume to OpenAI parsing for user {$user->id}");
                            } catch (\Exception $aiError) {
                                Log::error("OpenAI parsing failed (keeping default data): " . $aiError->getMessage());
                                // Not critical - we still have default parsing
                            }
                        } catch (\Exception $e) {
                            Log::error("Failed to save parsed resume from guest data: " . $e->getMessage());
                            // Fall back to full OpenAI parsing
                            try {
                                $resumeParserService->parseResumeFromStorage($newPath, $user->id);
                            } catch (\Exception $parseError) {
                                Log::error("Fallback parsing also failed: " . $parseError->getMessage());
                            }
                        }
                    }
                }
            }

            // Clean up guest session data
            $guestPath = "guest-uploads/{$sessionId}";
            if (Storage::exists($guestPath)) {
                Storage::deleteDirectory($guestPath);
            }
            session()->forget(['guest_uploads', 'guest_parsed_resume', 'guest_matched_jobs']);
        }

        return $movedFiles;
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        $hasGoogleAuth = $user && !empty($user->google_id);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $response = ['message' => 'Logged out successfully'];

        // Note: Google OAuth session remains active in browser
        // Users will see account picker on next "Continue with Google" due to prompt=select_account
        if ($hasGoogleAuth) {
            Log::info('Logged out user with Google OAuth', ['user_id' => $user->id]);
            $response['had_google_oauth'] = true;
        }

        return response()->json($response);
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
            'user' => $user,
            'prelaunch' => env('PRELAUNCH', false)
        ]);
    }
    public function claimGuestUploads(Request $request)
    {
        $user = Auth::user();
        $userPath = "user-uploads/{$user->id}";
        $movedFiles = [];
        $resumeParserService = app(ResumeParserService::class);

        // Get guest uploads from current session first
        $guestUploads = session('guest_uploads', []);
        $sessionId = session()->getId();
        
        if (!empty($guestUploads)) {
            // Process files from session metadata
            foreach ($guestUploads as $uploadInfo) {
                if (Storage::exists($uploadInfo['path'])) {
                    $newPath = "{$userPath}/{$uploadInfo['stored_name']}";
                    Storage::move($uploadInfo['path'], $newPath);
                    
                    // Create database record
                    $uploadedFile = UploadedFile::create([
                        'user_id' => $user->id,
                        'original_name' => $uploadInfo['original_name'],
                        'file_path' => $newPath,
                        'file_type' => 'resume', // Default to resume for now
                        'mime_type' => $uploadInfo['type'],
                        'file_size' => $uploadInfo['size'],
                        'is_active' => true,
                    ]);
                    
                    $movedFiles[] = [
                        'id' => $uploadedFile->id,
                        'original_name' => $uploadedFile->original_name,
                        'stored_name' => $uploadInfo['stored_name'],
                        'path' => $newPath,
                        'size' => $uploadedFile->file_size,
                        'type' => $uploadedFile->mime_type,
                        'created_at' => $uploadedFile->created_at->toISOString()
                    ];

                    // Parse resume if it's a PDF (resume file)
                    if ($uploadedFile->file_type === 'resume' && in_array($uploadedFile->mime_type, ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/msword', 'text/plain'])) {
                        try {
                            Log::info("Parsing claimed resume for user {$user->id}", ['file_id' => $uploadedFile->id]);
                            $resumeParserService->parseResumeFromStorage($newPath, $user->id);
                        } catch (\Exception $e) {
                            Log::error("Failed to parse claimed resume: " . $e->getMessage(), [
                                'user_id' => $user->id,
                                'file_id' => $uploadedFile->id,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                }
            }
            
            // Clean up current session directory
            $guestPath = "guest-uploads/{$sessionId}";
            if (Storage::exists($guestPath)) {
                Storage::deleteDirectory($guestPath);
            }
            session()->forget('guest_uploads');
        }
        
        // Fallback: Check if user already has files in database
        if (empty($movedFiles)) {
            $existingUploads = $user->uploadedFiles()->where('is_active', true)->get();
            foreach ($existingUploads as $uploadedFile) {
                $movedFiles[] = [
                    'id' => $uploadedFile->id,
                    'original_name' => $uploadedFile->original_name,
                    'stored_name' => basename($uploadedFile->file_path),
                    'path' => $uploadedFile->file_path,
                    'size' => $uploadedFile->file_size,
                    'type' => $uploadedFile->mime_type,
                    'created_at' => $uploadedFile->created_at->toISOString()
                ];
            }
        }
        
        // Last resort: Check recent guest upload directories (in case session was lost)
        if (empty($movedFiles)) {
            $guestDirectories = Storage::directories('guest-uploads');
            
            // Sort by modification time and check the most recent ones
            usort($guestDirectories, function($a, $b) {
                return Storage::lastModified($b) - Storage::lastModified($a);
            });
            
            // Check the 3 most recent guest directories
            foreach (array_slice($guestDirectories, 0, 3) as $guestDir) {
                $files = Storage::files($guestDir);
                if (!empty($files)) {
                    foreach ($files as $file) {
                        $fileName = basename($file);
                        $newPath = "{$userPath}/{$fileName}";
                        Storage::move($file, $newPath);
                        
                        // Create database record for moved file
                        $uploadedFile = UploadedFile::create([
                            'user_id' => $user->id,
                            'original_name' => $fileName,
                            'file_path' => $newPath,
                            'file_type' => 'resume', // Default to resume
                            'mime_type' => Storage::mimeType($newPath) ?: 'application/octet-stream',
                            'file_size' => Storage::size($newPath),
                            'is_active' => true,
                        ]);
                        
                        $movedFiles[] = [
                            'id' => $uploadedFile->id,
                            'original_name' => $uploadedFile->original_name,
                            'stored_name' => $fileName,
                            'path' => $newPath,
                            'size' => $uploadedFile->file_size,
                            'type' => $uploadedFile->mime_type,
                            'created_at' => $uploadedFile->created_at->toISOString()
                        ];

                        // Parse resume if it's a PDF (resume file)
                        if ($uploadedFile->file_type === 'resume' && in_array($uploadedFile->mime_type, ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/msword', 'text/plain'])) {
                            try {
                                Log::info("Parsing claimed resume for user {$user->id}", ['file_id' => $uploadedFile->id]);
                                $resumeParserService->parseResumeFromStorage($newPath, $user->id);
                            } catch (\Exception $e) {
                                Log::error("Failed to parse claimed resume: " . $e->getMessage(), [
                                    'user_id' => $user->id,
                                    'file_id' => $uploadedFile->id,
                                    'error' => $e->getMessage()
                                ]);
                            }
                        }
                    }

                    // Clean up the guest directory
                    Storage::deleteDirectory($guestDir);
                    break; // Only process one directory
                }
            }
        }
        
        if (!empty($movedFiles)) {
            return response()->json([
                'message' => 'Files claimed successfully',
                'files' => $movedFiles,
                'count' => count($movedFiles)
            ]);
        }
        
        return response()->json(['message' => 'No files to claim']);
    }
}