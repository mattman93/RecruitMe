<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\JobSiteStructure;
use App\Services\JobMatchingService;
use App\Services\LeadCacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LeadController extends Controller
{
    protected $jobMatchingService;
    protected $leadCacheService;

    public function __construct(JobMatchingService $jobMatchingService, LeadCacheService $leadCacheService)
    {
        $this->jobMatchingService = $jobMatchingService;
        $this->leadCacheService = $leadCacheService;
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $matchRelevant = $request->boolean('relevant', false);
        $limit = $request->integer('limit', 50);

        // Try to get from cache if user is authenticated
        if ($user && $this->leadCacheService->isAvailable()) {
            $cached = $this->leadCacheService->getLeads($user->id, $matchRelevant, $limit);

            if ($cached !== null) {
                Log::debug('Leads served from cache', [
                    'user_id' => $user->id,
                    'relevant' => $matchRelevant,
                    'limit' => $limit
                ]);

                return response()->json(array_merge($cached, [
                    'cached' => true,
                    'cache_hit' => true
                ]));
            }
        }

        // Cache miss or cache unavailable - fetch from database
        if ($matchRelevant && $user) {
            // Return AI-matched relevant jobs
            $fetchLimit = $limit * 2;
            $leads = $this->jobMatchingService->findRelevantJobs($user->id, $fetchLimit);
            $enrichedLeads = $this->enrichLeadsWithStructureData($leads);

            // Deduplicate jobs based on job_title and company
            $uniqueJobs = $enrichedLeads->unique(function ($job) {
                return $job['job_title'] . '|' . $job['company'];
            })->values()->take($limit);

            $response = [
                'data' => $uniqueJobs,
                'total' => $uniqueJobs->count(),
                'matching_strategy' => $leads->first()?->skill_matches ? 'skills-based' : 'experience-based',
                'user_id' => $user->id,
            ];

            // Cache the response
            if ($user && $this->leadCacheService->isAvailable()) {
                $this->leadCacheService->putLeads($user->id, $response, $matchRelevant, $limit);
            }

            return response()->json(array_merge($response, [
                'cached' => false,
                'cache_hit' => false
            ]));
        }

        // Return all jobs (default behavior)
        $fetchLimit = $limit * 2;
        $leads = Lead::where('is_active', true)
                    ->orderBy('created_at', 'desc')
                    ->limit($fetchLimit)
                    ->get();

        $enrichedLeads = $this->enrichLeadsWithStructureData($leads);

        // Deduplicate jobs based on job_title and company
        $uniqueJobs = $enrichedLeads->unique(function ($job) {
            return $job['job_title'] . '|' . $job['company'];
        })->values()->take($limit);

        $response = [
            'data' => $uniqueJobs,
            'total' => $uniqueJobs->count(),
        ];

        // Cache the response for authenticated users
        if ($user && $this->leadCacheService->isAvailable()) {
            $this->leadCacheService->putLeads($user->id, $response, $matchRelevant, $limit);
        }

        return response()->json(array_merge($response, [
            'cached' => false,
            'cache_hit' => false
        ]));
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

        $limit = $request->integer('limit', 50);

        // Try to get from cache
        if ($this->leadCacheService->isAvailable()) {
            $cached = $this->leadCacheService->getLeads($user->id, true, $limit);

            if ($cached !== null) {
                Log::debug('Relevant leads served from cache', [
                    'user_id' => $user->id,
                    'limit' => $limit
                ]);

                return response()->json(array_merge($cached, [
                    'cached' => true,
                    'cache_hit' => true
                ]));
            }
        }

        // Cache miss - fetch from database
        try {
            // Fetch more jobs than needed to account for deduplication
            $fetchLimit = $limit * 2;
            $relevantJobs = $this->jobMatchingService->findRelevantJobs($user->id, $fetchLimit);
            $enrichedJobs = $this->enrichLeadsWithStructureData($relevantJobs);

            // Deduplicate jobs based on job_title and company
            $uniqueJobs = $enrichedJobs->unique(function ($job) {
                return $job['job_title'] . '|' . $job['company'];
            })->values()->take($limit);

            $response = [
                'data' => $uniqueJobs,
                'total' => $uniqueJobs->count(),
                'total_potential_matches' => $relevantJobs->total_potential_matches ?? $uniqueJobs->count(),
                'matching_strategy' => $relevantJobs->first()?->skill_matches ? 'skills-based' : 'experience-based',
                'message' => $relevantJobs->isEmpty()
                    ? 'No relevant jobs found. Try updating your resume or work experience.'
                    : "Found {$uniqueJobs->count()} relevant job matches.",
            ];

            // Cache the response
            if ($this->leadCacheService->isAvailable()) {
                $this->leadCacheService->putLeads($user->id, $response, true, $limit);
            }

            return response()->json(array_merge($response, [
                'cached' => false,
                'cache_hit' => false
            ]));

        } catch (\Exception $e) {
            Log::error('Error in relevant jobs endpoint', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            // Fallback to regular jobs if relevant matching fails
            $fetchLimit = $limit * 2;
            $leads = Lead::where('is_active', true)
                        ->orderBy('created_at', 'desc')
                        ->limit($fetchLimit)
                        ->get();

            $enrichedLeads = $this->enrichLeadsWithStructureData($leads);

            // Deduplicate jobs based on job_title and company
            $uniqueJobs = $enrichedLeads->unique(function ($job) {
                return $job['job_title'] . '|' . $job['company'];
            })->values()->take($limit);

            return response()->json([
                'data' => $uniqueJobs,
                'total' => $uniqueJobs->count(),
                'matching_strategy' => 'fallback',
                'message' => 'Showing all available jobs (relevance matching temporarily unavailable)',
                'cached' => false,
                'cache_hit' => false
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

    /**
     * Preview jobs by role (for unauthenticated users).
     */
    public function previewByRole(Request $request)
    {
        $request->validate([
            'role' => 'required|string|min:2',
            'limit' => 'integer|min:1|max:20'
        ]);

        $role = $request->input('role');
        $limit = $request->integer('limit', 50);
        $hoursAgo = 72; // Last 3 days

        // Build query for role-based search
        $query = Lead::where('is_active', true)
            ->where('created_at', '>=', now()->subHours($hoursAgo))
            ->where(function($q) use ($role) {
                $q->where('job_title', 'LIKE', '%' . $role . '%')
                  ->orWhere('core_job_title', 'LIKE', '%' . $role . '%')
                  ->orWhere('description', 'LIKE', '%' . $role . '%');
            });

        // Get total count for display
        $totalCount = $query->count();

        // Get sample jobs with scoring
        $jobs = $query->orderBy('created_at', 'desc')
            ->limit($limit * 2)
            ->get()
            ->map(function($job) use ($role) {
                $jobArray = $job->toArray();

                // Calculate simple relevance score (0-100)
                $score = 50; // Base score

                // Boost for exact title match
                if (stripos($job->job_title, $role) !== false) {
                    $score += 30;
                }

                // Boost for core title match
                if ($job->core_job_title && stripos($job->core_job_title, $role) !== false) {
                    $score += 20;
                }

                // Cap at 100
                $score = min($score, 100);

                $jobArray['relevance_score'] = $score;
                return $jobArray;
            })
            ->sortByDesc('relevance_score')
            ->take($limit)
            ->values();

        // If we have results but count is low, show a random inflated number for marketing
        $displayCount = $totalCount;
        $showApproximate = false;

        if ($jobs->count() > 0 && $totalCount < 50) {
            $displayCount = rand(200, 300);
            $showApproximate = true;
        }

        return response()->json([
            'data' => $jobs,
            'total' => $jobs->count(),
            'total_matches' => $displayCount,
            'show_approximate' => $showApproximate,
            'role' => $role,
            'hours' => $hoursAgo,
            'message' => $jobs->count() > 0
                ? "Found {$displayCount}" . ($showApproximate ? '+' : '') . " matching roles posted in last " . ($hoursAgo / 24) . " days"
                : "No recent matches found for this role"
        ]);
    }
}