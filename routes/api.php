<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GuestUploadController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\UserController;

// All API routes need web middleware to share sessions
Route::middleware(['web'])->group(function () {
    Route::get('/auth/check', [AuthController::class, 'check']);
    Route::get('/csrf-token', function () {
    return response()->json(['token' => csrf_token()]);
});
    // Guest upload endpoints
    Route::post('/guest/resume-upload', [GuestUploadController::class, 'upload']);
    Route::get('/guest/resume-preview/{sessionId}', [GuestUploadController::class, 'preview']);

    // Protected routes
    Route::middleware(['auth'])->group(function () {
        Route::get('/leads', [LeadController::class, 'index']);
        Route::get('/user/resume', [UserController::class, 'getResume']);
        Route::post('/user/claim-guest-uploads', [AuthController::class, 'claimGuestUploads']);
    });
});