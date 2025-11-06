<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GuestUploadController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ApiResumeParseController;
use App\Http\Controllers\Api\FormPreferenceController;

// API routes use Sanctum's EnsureFrontendRequestsAreStateful middleware for sessions
Route::get('/auth/check', [AuthController::class, 'check']);

// Guest upload endpoints
Route::post('/guest/resume-upload', [GuestUploadController::class, 'upload']);
Route::get('/guest/resume-preview/{sessionId}', [GuestUploadController::class, 'preview']);

// Batch status endpoint - supports both token and session auth
Route::get('/applications/batch-status/{batchId}', [\App\Http\Controllers\Api\JobApplicationController::class, 'batchStatus']);

// Leads endpoints - handle auth internally
Route::get('/leads', [LeadController::class, 'index']);
Route::get('/leads/relevant', [LeadController::class, 'relevant']);
Route::get('/leads/preview-by-role', [LeadController::class, 'previewByRole']);

// Form preferences endpoint for learning system (POST only - GET is protected)
Route::post('/form-preferences', [FormPreferenceController::class, 'store']);

// Mailing list endpoint
Route::post('/mailing-list/subscribe', [\App\Http\Controllers\MailListController::class, 'subscribe']);

// Beta access token validation
Route::post('/beta/activate', [\App\Http\Controllers\BetaAccessController::class, 'activate']);
Route::get('/beta/check', [\App\Http\Controllers\BetaAccessController::class, 'check']);

// Stripe webhook endpoint (must be outside auth middleware)
Route::post('/stripe/webhook', [\App\Http\Controllers\Api\StripeWebhookController::class, 'handleWebhook']);

// Stripe pricing configuration (public endpoint - unauthenticated users need to see pricing)
Route::get('/stripe/pricing-config', [\App\Http\Controllers\Api\StripeController::class, 'getPricingConfig']);

// Stripe checkout endpoints (public - support both authenticated and unauthenticated users)
Route::post('/stripe/create-checkout-session', [\App\Http\Controllers\Api\StripeController::class, 'createCheckoutSession']);
Route::get('/stripe/session-status', [\App\Http\Controllers\Api\StripeController::class, 'getSessionStatus']);

// Protected routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user/resume', [\App\Http\Controllers\Api\UserController::class, 'getResume']);
    Route::get('/user/files', [\App\Http\Controllers\Api\UserController::class, 'getAllFiles']);
    Route::get('/user/file/{id}', [\App\Http\Controllers\Api\UserController::class, 'downloadFile']);
    Route::get('/user/work-experience', [\App\Http\Controllers\Api\UserController::class, 'getWorkExperience']);
    Route::get('/user/application-form-data', [\App\Http\Controllers\Api\UserController::class, 'getApplicationFormData']);
    Route::get('/user/oauth-status', [\App\Http\Controllers\Api\UserController::class, 'getOAuthStatus']);
    Route::get('/user/flow-rank', [\App\Http\Controllers\Api\UserController::class, 'getFlowRank']);
    Route::get('/user/has-resume', [\App\Http\Controllers\Api\UserController::class, 'hasResume']);
    Route::get('/user/credits', [\App\Http\Controllers\Api\UserController::class, 'getCredits']);
    Route::get('/user/resume-replacement-status', [\App\Http\Controllers\Api\UserController::class, 'getResumeReplacementStatus']);
    Route::delete('/user/delete-account', [\App\Http\Controllers\Api\UserController::class, 'deleteAccount']);

    // User Settings
    Route::get('/user/settings', [\App\Http\Controllers\Api\UserSettingsController::class, 'index']);
    Route::post('/user/settings', [\App\Http\Controllers\Api\UserSettingsController::class, 'update']);

    // Resume Parsing API endpoints
    Route::apiResource('resume/parse', ApiResumeParseController::class);
    Route::post('/resume/parse/{id}/match', [ApiResumeParseController::class, 'matchJob']);
    Route::post('/resume/parse/{id}/reparse', [ApiResumeParseController::class, 'reparse']);
    
    // Job Application endpoints
    Route::apiResource('applications', \App\Http\Controllers\Api\JobApplicationController::class);
    Route::post('/applications/queue', [\App\Http\Controllers\Api\JobApplicationController::class, 'queueApplications']);
    Route::post('/applications/process', [\App\Http\Controllers\Api\JobApplicationController::class, 'processApplications']);
    Route::patch('/applications/{id}/status', [\App\Http\Controllers\Api\JobApplicationController::class, 'updateStatus']);
    Route::get('/applications/analytics/summary', [\App\Http\Controllers\Api\JobApplicationController::class, 'analytics']);
    
    // NEW: Playwright + LLM automation endpoints
    Route::post('/automation/process-application', [\App\Http\Controllers\Api\JobApplicationController::class, 'processApplication']);
    Route::post('/automation/process-application-async', [\App\Http\Controllers\Api\JobApplicationController::class, 'processApplicationAsync']);
    Route::get('/automation/status/{sessionKey}', [\App\Http\Controllers\Api\JobApplicationController::class, 'getApplicationStatus']);
    Route::post('/automation/submit-missing-fields', [\App\Http\Controllers\Api\JobApplicationController::class, 'submitMissingFields']);
    Route::post('/automation/submit-application', [\App\Http\Controllers\Api\JobApplicationController::class, 'submitApplication']);

    // NEW: Email-based application endpoints
    Route::post('/automation/process-email-application', [\App\Http\Controllers\Api\JobApplicationController::class, 'processEmailApplication']);
    Route::post('/automation/submit-preferences-and-apply', [\App\Http\Controllers\Api\JobApplicationController::class, 'submitPreferencesAndApply']);
    
    // Form Preferences protected endpoints
    Route::get('/form-preferences', [FormPreferenceController::class, 'getUserPreferences']);
    Route::post('/form-preferences/find', [FormPreferenceController::class, 'findPreference']);
    Route::patch('/form-preferences/confidence', [FormPreferenceController::class, 'updateConfidence']);

    // Admin routes - Super Admin only
    Route::prefix('admin')->group(function () {
        Route::get('/scheduler-stats', [\App\Http\Controllers\Api\Admin\SchedulerStatsController::class, 'index']);
        Route::get('/scheduler-stats/summary', [\App\Http\Controllers\Api\Admin\SchedulerStatsController::class, 'summary']);
    });

    // Debug route to test authentication
    Route::get('/test-auth', function() {
        \Log::info('Test auth route hit', ['user_id' => \Auth::id(), 'authenticated' => \Auth::check()]);
        return response()->json(['authenticated' => \Auth::check(), 'user_id' => \Auth::id()]);
    });
});