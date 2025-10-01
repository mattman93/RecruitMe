<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserFormPreference;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FormPreferenceController extends Controller
{
    /**
     * Store a user's form field preference
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'question_text' => 'required|string',
            'field_type' => 'required|string|in:yes_no,select,radio,checkbox,text',
            'response_data' => 'required',
        ]);

        // Get user ID from auth
        $userId = auth()->id();
        
        // For job proxy iframe environment, allow using session-based user ID
        if (!$userId) {
            $referer = $request->header('Referer', '');
            
            // Only allow for job proxy context (not test-learning)
            if (str_contains($referer, 'job-proxy')) {
                // Try to get user ID from session or use authenticated context
                $userId = $request->session()->get('user_id') ?? auth('web')->id();
                if (!$userId) {
                    \Log::warning('Learning system: No user ID available in job proxy context', [
                        'referer' => $referer
                    ]);
                    return response()->json(['error' => 'User not authenticated'], 401);
                }
            } else {
                return response()->json(['error' => 'User not authenticated'], 401);
            }
        }

        $preference = UserFormPreference::storePreference(
            $userId,
            $request->question_text,
            $request->field_type,
            $request->response_data
        );

        return response()->json([
            'success' => true,
            'preference' => $preference,
            'message' => 'Form preference stored successfully'
        ]);
    }

    /**
     * Get user's form preferences for auto-fill
     */
    public function getUserPreferences(Request $request): JsonResponse
    {
        $userId = auth()->id();
        
        if (!$userId) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $preferences = UserFormPreference::where('user_id', $userId)
            ->orderBy('use_count', 'desc')
            ->orderBy('confidence_score', 'desc')
            ->get();

        return response()->json([
            'preferences' => $preferences
        ]);
    }

    /**
     * Find preference for a specific question
     */
    public function findPreference(Request $request): JsonResponse
    {
        $request->validate([
            'question_text' => 'required|string'
        ]);

        $userId = auth()->id();
        if (!$userId) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $preference = UserFormPreference::findByQuestionPattern(
            $userId, 
            $request->question_text
        );

        if ($preference) {
            // Increment use count when preference is found and used
            $preference->increment('use_count');
            
            return response()->json([
                'found' => true,
                'preference' => $preference
            ]);
        }

        return response()->json([
            'found' => false,
            'message' => 'No preference found for this question'
        ]);
    }

    /**
     * Update confidence score for a preference (for machine learning feedback)
     */
    public function updateConfidence(Request $request): JsonResponse
    {
        $request->validate([
            'preference_id' => 'required|integer|exists:user_form_preferences,id',
            'confidence_adjustment' => 'required|integer|min:-50|max:50'
        ]);

        $userId = auth()->id();
        if (!$userId) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $preference = UserFormPreference::where('id', $request->preference_id)
            ->where('user_id', $userId)
            ->first();

        if (!$preference) {
            return response()->json(['error' => 'Preference not found'], 404);
        }

        $newScore = max(0, min(100, $preference->confidence_score + $request->confidence_adjustment));
        $preference->update(['confidence_score' => $newScore]);

        return response()->json([
            'success' => true,
            'new_confidence_score' => $newScore
        ]);
    }
}
