<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GuestUploadController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ApiResumeParseController;

// API routes use Sanctum's EnsureFrontendRequestsAreStateful middleware for sessions
Route::get('/auth/check', [AuthController::class, 'check']);

// Guest upload endpoints
Route::post('/guest/resume-upload', [GuestUploadController::class, 'upload']);
Route::get('/guest/resume-preview/{sessionId}', [GuestUploadController::class, 'preview']);

// Protected routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/leads', [LeadController::class, 'index']);
    Route::get('/leads/relevant', [LeadController::class, 'relevant']);
    Route::get('/user/resume', [\App\Http\Controllers\Api\UserController::class, 'getResume']);
    Route::get('/user/files', [\App\Http\Controllers\Api\UserController::class, 'getAllFiles']);
    Route::get('/user/file/{id}', [\App\Http\Controllers\Api\UserController::class, 'downloadFile']);
    Route::get('/user/work-experience', [\App\Http\Controllers\Api\UserController::class, 'getWorkExperience']);
    
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
});