<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserOAuthToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class GoogleOAuthController extends Controller
{
    /**
     * Redirect the user to the Google authentication page
     */
    public function redirectToGoogle()
    {
        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email', 'https://www.googleapis.com/auth/gmail.send'])
            ->with([
                'prompt' => 'consent', // Changed from 'select_account' to force consent screen
                'access_type' => 'offline', // Request refresh token
                'include_granted_scopes' => 'true' // Include previously granted scopes
            ])
            ->redirect();
    }

    /**
     * Handle the callback from Google
     */
    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            Log::info('Google OAuth callback received', [
                'google_id' => $googleUser->id,
                'email' => $googleUser->email,
                'name' => $googleUser->name
            ]);

            // Find or create user
            $user = User::where('email', $googleUser->email)->first();

            if (!$user) {
                // Create new user
                $user = User::create([
                    'name' => $googleUser->name,
                    'email' => $googleUser->email,
                    'google_id' => $googleUser->id,
                    'avatar' => $googleUser->avatar,
                    'email_verified_at' => now(),
                ]);

                Log::info('Created new user from Google OAuth', ['user_id' => $user->id]);
            } else {
                // Update existing user with Google info
                $user->update([
                    'google_id' => $googleUser->id,
                    'avatar' => $googleUser->avatar,
                    'email_verified_at' => $user->email_verified_at ?? now(), // Verify email if not already verified
                ]);

                Log::info('Updated existing user with Google OAuth', ['user_id' => $user->id]);
            }

            // Store OAuth tokens for email sending
            $this->storeGoogleTokens($user, $googleUser);

            // Log the user in
            Auth::login($user);

            // Regenerate session for security
            request()->session()->regenerate();

            // Smart redirect based on user status
            return $this->smartRedirect($user);

        } catch (\Exception $e) {
            Log::error('Google OAuth failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect('/login')->with('error', 'Google authentication failed. Please try again.');
        }
    }

    /**
     * Store Google OAuth tokens for sending emails
     */
    protected function storeGoogleTokens(User $user, $googleUser)
    {
        // Store tokens in user_oauth_tokens table (we'll create this)
        $tokenData = [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => $googleUser->id,
            'access_token' => $googleUser->token,
            'refresh_token' => $googleUser->refreshToken,
            'expires_at' => $googleUser->expiresIn ? now()->addSeconds($googleUser->expiresIn) : null,
            'scopes' => json_encode(['https://www.googleapis.com/auth/gmail.send', 'profile', 'email']),
        ];

        // For now, log the tokens (in production, store in database)
        Log::info('Google OAuth tokens received', [
            'user_id' => $user->id,
            'has_access_token' => !empty($googleUser->token),
            'has_refresh_token' => !empty($googleUser->refreshToken),
            'expires_in' => $googleUser->expiresIn,
            'scopes' => 'gmail.send,profile,email'
        ]);

        // Store tokens in database using DB facade (workaround for Eloquent issue)
        $existingToken = \DB::table('user_oauth_tokens')
            ->where('user_id', $user->id)
            ->where('provider', 'google')
            ->first();

        if ($existingToken) {
            \DB::table('user_oauth_tokens')
                ->where('user_id', $user->id)
                ->where('provider', 'google')
                ->update([
                    'provider_user_id' => $tokenData['provider_user_id'],
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => $tokenData['refresh_token'],
                    'expires_at' => $tokenData['expires_at'],
                    'scopes' => $tokenData['scopes'],
                    'updated_at' => now()
                ]);
        } else {
            \DB::table('user_oauth_tokens')->insert([
                'user_id' => $tokenData['user_id'],
                'provider' => $tokenData['provider'],
                'provider_user_id' => $tokenData['provider_user_id'],
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'],
                'expires_at' => $tokenData['expires_at'],
                'scopes' => $tokenData['scopes'],
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }

    /**
     * Smart redirect logic based on user status
     */
    protected function smartRedirect(User $user)
    {
        // Check if user has uploaded a resume
        if (!$user->hasResume()) {
            Log::info('New user without resume, redirecting to upload page', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            return redirect('/upload')->with('success', 'Welcome! Please upload your resume to get started.');
        }

        Log::info('Existing user with resume, redirecting to dashboard', [
            'user_id' => $user->id,
            'email' => $user->email,
            'has_resume' => true
        ]);

        return redirect('/dashboard')->with('success', 'Successfully signed in with Google!');
    }

    /**
     * Test endpoint to check OAuth tokens
     */
    public function testGmailAccess()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        // This would check if we can send emails via Gmail API
        Log::info('Testing Gmail access for user', ['user_id' => $user->id]);

        return response()->json([
            'user_id' => $user->id,
            'email' => $user->email,
            'google_connected' => !empty($user->google_id),
            'message' => 'OAuth tokens would be checked here for Gmail sending capability'
        ]);
    }
}