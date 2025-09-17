<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Services\JobMatchingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeadController extends Controller
{
    protected $jobMatchingService;

    public function __construct(JobMatchingService $jobMatchingService)
    {
        $this->jobMatchingService = $jobMatchingService;
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $matchRelevant = $request->boolean('relevant', false);
        $limit = $request->integer('limit', 20);

        if ($matchRelevant && $user) {
            // Return AI-matched relevant jobs
            $leads = $this->jobMatchingService->findRelevantJobs($user->id, $limit);
            
            return response()->json([
                'data' => $leads->values(),
                'total' => $leads->count(),
                'matching_strategy' => $leads->first()?->skill_matches ? 'skills-based' : 'experience-based',
                'user_id' => $user->id,
            ]);
        }

        // Return all jobs (default behavior)
        $leads = Lead::where('is_active', true)
                    ->orderBy('created_at', 'desc')
                    ->paginate($limit);

        return response()->json($leads);
    }

    /**
     * Get relevant jobs for the authenticated user.
     */
    public function relevant(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'error' => 'Authentication required'
            ], 401);
        }

        try {
            $limit = $request->integer('limit', 50);
            $relevantJobs = $this->jobMatchingService->findRelevantJobs($user->id, $limit);

            return response()->json([
                'data' => $relevantJobs->values(),
                'total' => $relevantJobs->count(),
                'matching_strategy' => $relevantJobs->first()?->skill_matches ? 'skills-based' : 'experience-based',
                'message' => $relevantJobs->isEmpty() 
                    ? 'No relevant jobs found. Try updating your resume or work experience.' 
                    : "Found {$relevantJobs->count()} relevant job matches.",
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in relevant jobs endpoint', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            // Fallback to regular jobs if relevant matching fails
            $leads = Lead::where('is_active', true)
                        ->orderBy('created_at', 'desc')
                        ->limit($limit)
                        ->get();

            return response()->json([
                'data' => $leads,
                'total' => $leads->count(),
                'matching_strategy' => 'fallback',
                'message' => 'Showing all available jobs (relevance matching temporarily unavailable)',
            ]);
        }
    }
}