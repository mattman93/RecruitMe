<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\JobSiteStructure;
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
            $enrichedLeads = $this->enrichLeadsWithStructureData($leads);
            
            return response()->json([
                'data' => $enrichedLeads->values(),
                'total' => $enrichedLeads->count(),
                'matching_strategy' => $leads->first()?->skill_matches ? 'skills-based' : 'experience-based',
                'user_id' => $user->id,
            ]);
        }

        // Return all jobs (default behavior)
        $leads = Lead::where('is_active', true)
                    ->orderBy('created_at', 'desc')
                    ->limit($limit)
                    ->get();
                    
        $enrichedLeads = $this->enrichLeadsWithStructureData($leads);

        return response()->json([
            'data' => $enrichedLeads,
            'total' => $enrichedLeads->count(),
        ]);
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
            $enrichedJobs = $this->enrichLeadsWithStructureData($relevantJobs);

            return response()->json([
                'data' => $enrichedJobs->values(),
                'total' => $enrichedJobs->count(),
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
                        
            $enrichedLeads = $this->enrichLeadsWithStructureData($leads);

            return response()->json([
                'data' => $enrichedLeads,
                'total' => $enrichedLeads->count(),
                'matching_strategy' => 'fallback',
                'message' => 'Showing all available jobs (relevance matching temporarily unavailable)',
            ]);
        }
    }

    /**
     * Enrich leads with job site structure data for auto-fill
     */
    protected function enrichLeadsWithStructureData($leads)
    {
        return $leads->map(function ($lead) {
            $leadArray = $lead->toArray();
            
            // Get job site structure data
            $siteStructure = $lead->jobSiteStructure();
            
            if ($siteStructure) {
                $leadArray['auto_fill_data'] = [
                    'has_structure' => true,
                    'platform_name' => $siteStructure->platform_name,
                    'automation_strategy' => $siteStructure->automation_strategy,
                    'field_mappings' => $siteStructure->field_mappings ?? [],
                    'form_fields' => $siteStructure->form_fields ?? [],
                    'button_selectors' => $siteStructure->button_selectors ?? [],
                    'success_indicators' => $siteStructure->success_indicators ?? [],
                    'has_captcha' => $siteStructure->has_captcha,
                    'success_rate' => $siteStructure->success_rate,
                ];
            } else {
                $leadArray['auto_fill_data'] = [
                    'has_structure' => false,
                    'platform_name' => 'Unknown',
                    'automation_strategy' => 'semi_auto',
                    'field_mappings' => [],
                    'form_fields' => [],
                    'button_selectors' => [],
                    'success_indicators' => [],
                    'has_captcha' => false,
                    'success_rate' => null,
                ];
            }
            
            return $leadArray;
        });
    }
}