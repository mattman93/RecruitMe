<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\GoogleOAuthController;
use App\Http\Controllers\ResumeController;
use App\Http\Controllers\JobProxyController;

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Google OAuth routes
Route::get('/auth/google', [GoogleOAuthController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleOAuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');
Route::get('/auth/google/test', [GoogleOAuthController::class, 'testGmailAccess'])->name('auth.google.test');

// API authentication routes that need full session support
Route::prefix('api')->group(function () {
    Route::get('/csrf-token', function () {
        return response()->json(['token' => csrf_token()]);
    });
    Route::post('/login', [\App\Http\Controllers\Api\AuthController::class, 'login']);
    Route::post('/register', [\App\Http\Controllers\Api\AuthController::class, 'register']);
    Route::post('/logout', [\App\Http\Controllers\Api\AuthController::class, 'logout']);
    
    // Session-dependent routes
    Route::middleware(['auth'])->group(function () {
        Route::post('/user/claim-guest-uploads', [\App\Http\Controllers\Api\AuthController::class, 'claimGuestUploads']);
    });
    
    // TEMPORARY: Test automation endpoint without any middleware (including CSRF)
    Route::post('/automation/process-application-test', function(\Illuminate\Http\Request $request) {
        try {
            \Log::info('Test automation endpoint called from web routes', $request->all());
            
            // Get user (hardcoded for testing)
            $user = \App\Models\User::find(3);
            if (!$user) {
                return response()->json(['error' => 'User not found'], 404);
            }
            
            \Illuminate\Support\Facades\Auth::login($user);
            \Log::info('User authenticated for test', ['user_id' => $user->id]);
            
            $playwrightService = app(\App\Services\PlaywrightAutomationService::class);
            
            $result = $playwrightService->processJobApplicationWithLLM(
                $request->job_url ?: 'https://job-boards.greenhouse.io/dorsia/jobs/4771546007',
                $user->id,
                $request->user_form_data ?: [
                    'firstName' => 'Matthew',
                    'lastName' => 'Cieslak',
                    'email' => 'mattcieslak93@gmail.com',
                    'phone' => '(908) 590-0741',
                    'currentLocation' => 'Williamstown, NJ'
                ]
            );
            
            \Log::info('Test automation completed', $result);
            
            return response()->json([
                'success' => true,
                'result' => $result,
                'message' => 'Test automation completed successfully'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Test automation failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    });
});

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
Route::post('/register', [RegisterController::class, 'register']);

// Password reset routes (Laravel's built-in)
/*
Route::get('password/reset', 'Auth\ForgotPasswordController@showLinkRequestForm')->name('password.request');
Route::post('password/email', 'Auth\ForgotPasswordController@sendResetLinkEmail')->name('password.email');
Route::get('password/reset/{token}', 'Auth\ResetPasswordController@showResetForm')->name('password.reset');
Route::post('password/reset', 'Auth\ResetPasswordController@reset')->name('password.update');
*/
// Protected routes
Route::middleware('auth')->group(function () {
    Route::post('/resume/upload', [ResumeController::class, 'upload'])->name('resume.upload');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth'])->name('dashboard');

// Upload route for new users without resume
Route::get('/upload', function () {
    return Inertia::render('Welcome'); // Use existing Welcome page with FileUpload
})->middleware(['auth'])->name('upload');

// Subscription routes (public - allow unauthenticated users to subscribe)
Route::get('/subscribe', function () {
    return Inertia::render('Welcome'); // Load the React SPA which will handle the subscribe state
})->name('subscribe');

Route::get('/subscribe/success', function () {
    return Inertia::render('Welcome'); // Load the React SPA which will handle the subscribe-success state
})->name('subscribe.success');

// Enterprise route
Route::get('/enterprise', function () {
    return Inertia::render('Welcome'); // Load the React SPA which will handle the enterprise state
})->name('enterprise');

// Privacy and Terms routes (public)
Route::get('/privacy', function () {
    return Inertia::render('Welcome'); // Load the React SPA which will handle the privacy state
})->name('privacy');

Route::get('/terms', function () {
    return Inertia::render('Welcome'); // Load the React SPA which will handle the terms state
})->name('terms');

// Admin routes - Super Admin only
Route::get('/admin/data-ingestion', function () {
    return Inertia::render('Welcome'); // Load the React SPA which will handle the admin state
})->middleware(['auth'])->name('admin.data-ingestion');

// Job site proxy routes
Route::get('/proxy/job-site', [JobProxyController::class, 'proxyJobSite'])->name('proxy.job-site');
Route::get('/job-proxy', [JobProxyController::class, 'proxyJobSite'])->name('job.proxy');

// Test route for learning system
Route::get('/test-learning', [\App\Http\Controllers\TestController::class, 'showTestForm'])->name('test.learning');
Route::get('/test-learning-proxy', function() {
    return redirect('/job-proxy?url=' . urlencode(url('/test-learning')));
})->name('test.learning.proxy');

// Form preferences endpoint moved back to API routes

