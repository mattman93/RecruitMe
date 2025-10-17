<?php

namespace App\Http\Controllers;

use App\Models\BetaAccessToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BetaAccessController extends Controller
{
    public function activate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => ['required', 'string', 'size:32'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid token format.',
                'errors' => $validator->errors()
            ], 422);
        }

        // Find the token
        $accessToken = BetaAccessToken::where('token', $request->token)->first();

        if (!$accessToken) {
            return response()->json([
                'message' => 'Invalid access code. Please check and try again.',
            ], 404);
        }

        // Check if user with this email already exists
        $user = User::where('email', $accessToken->email)->first();

        if (!$user) {
            // Create new user account with email
            $user = User::create([
                'email' => $accessToken->email,
                'name' => $accessToken->email, // Temporary - will be extracted from resume
                'password' => Hash::make(Str::random(32)), // Random password - not used during prelaunch
                'email_verified_at' => now(), // Auto-verify since they have beta token
            ]);
        }

        // Login the user
        Auth::login($user, true);

        // Mark token as activated with user ID
        if (!$accessToken->isActivated()) {
            $accessToken->activate($user->id);
        }

        // Store in session
        Session::put('beta_access_activated', true);
        Session::put('beta_access_email', $accessToken->email);
        Session::put('beta_access_token_id', $accessToken->id);

        return response()->json([
            'message' => 'Beta access activated successfully! Welcome to AppliFlow.',
            'email' => $accessToken->email,
        ]);
    }

    public function check()
    {
        $isActivated = Session::get('beta_access_activated', false);
        $email = Session::get('beta_access_email');

        return response()->json([
            'activated' => $isActivated,
            'email' => $email,
        ]);
    }
}
