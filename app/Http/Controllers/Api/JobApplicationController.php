<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Models\Lead;
use App\Services\JobApplicationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class JobApplicationController extends Controller
{
    protected JobApplicationService $applicationService;

    public function __construct(JobApplicationService $applicationService)
    {
        $this->applicationService = $applicationService;
    }

    /**
     * Get all applications for the authenticated user
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $applications = JobApplication::with(['lead', 'jobSiteStructure'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json($applications);
    }

    /**
     * Queue applications for selected jobs
     */
    public function queueApplications(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lead_ids' => 'required|array',
            'lead_ids.*' => 'required|integer|exists:leads,id',
            'custom_responses' => 'array',
            'application_method' => 'string|in:auto,semi_auto,iframe'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        $leadIds = $request->get('lead_ids');
        $customResponses = $request->get('custom_responses', []);
        $preferredMethod = $request->get('application_method');

        $queuedApplications = [];
        $errors = [];

        foreach ($leadIds as $leadId) {
            try {
                $lead = Lead::findOrFail($leadId);
                
                // Check if user already applied to this job
                $existingApplication = JobApplication::where('user_id', $user->id)
                    ->where('lead_id', $leadId)
                    ->first();

                if ($existingApplication) {
                    $errors[] = "Already applied to {$lead->job_title} at {$lead->company}";
                    continue;
                }

                $application = $this->applicationService->queueApplication(
                    $user, 
                    $lead, 
                    $customResponses[$leadId] ?? []
                );

                // Override strategy if user specified a preference
                if ($preferredMethod) {
                    $application->update(['application_method' => $preferredMethod]);
                }

                $queuedApplications[] = $application->load(['lead', 'jobSiteStructure']);

            } catch (\Exception $e) {
                $errors[] = "Failed to queue application for lead {$leadId}: " . $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'queued_applications' => $queuedApplications,
            'errors' => $errors,
            'message' => count($queuedApplications) . ' applications queued successfully'
        ]);
    }

    /**
     * Process queued applications
     */
    public function processApplications(Request $request)
    {
        $user = Auth::user();
        
        $applications = JobApplication::where('user_id', $user->id)
            ->where('status', 'queued')
            ->limit($request->get('batch_size', 5))
            ->get();

        $results = [];

        foreach ($applications as $application) {
            try {
                $success = $this->applicationService->processApplication($application);
                $results[] = [
                    'application_id' => $application->id,
                    'success' => $success,
                    'status' => $application->fresh()->status,
                    'message' => $success ? 'Application processed successfully' : 'Application processing failed'
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'application_id' => $application->id,
                    'success' => false,
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];
            }
        }

        return response()->json([
            'success' => true,
            'results' => $results,
            'processed_count' => count($results)
        ]);
    }

    /**
     * Get application status and details
     */
    public function show($id)
    {
        $user = Auth::user();
        
        $application = JobApplication::with(['lead', 'jobSiteStructure'])
            ->where('user_id', $user->id)
            ->findOrFail($id);

        return response()->json([
            'application' => $application,
            'automation_log_lines' => $application->automation_log ? 
                explode("\n", $application->automation_log) : [],
            'screenshots' => $application->screenshots ?? []
        ]);
    }

    /**
     * Update application status (for user confirmation)
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:submitted,failed,manual_required',
            'confirmation_message' => 'string|nullable',
            'notes' => 'string|nullable'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        
        $application = JobApplication::where('user_id', $user->id)
            ->findOrFail($id);

        $application->update([
            'status' => $request->get('status'),
            'confirmation_message' => $request->get('confirmation_message'),
            'completed_at' => in_array($request->get('status'), ['submitted', 'failed']) ? now() : null
        ]);

        // Add user notes to automation log
        if ($request->get('notes')) {
            $logEntry = "\n[" . now()->format('Y-m-d H:i:s') . "] User notes: " . $request->get('notes');
            $application->update([
                'automation_log' => ($application->automation_log ?? '') . $logEntry
            ]);
        }

        return response()->json([
            'success' => true,
            'application' => $application->fresh(),
            'message' => 'Application status updated successfully'
        ]);
    }

    /**
     * Get application analytics
     */
    public function analytics(Request $request)
    {
        $user = Auth::user();
        $timeframe = $request->get('timeframe', '30'); // days

        $baseQuery = JobApplication::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays($timeframe));

        $analytics = [
            'total_applications' => $baseQuery->count(),
            'status_breakdown' => $baseQuery->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
            'method_breakdown' => $baseQuery->selectRaw('application_method, COUNT(*) as count')
                ->groupBy('application_method')
                ->pluck('count', 'application_method'),
            'success_rate' => $this->calculateSuccessRate($baseQuery),
            'average_processing_time' => $this->calculateAverageProcessingTime($baseQuery),
            'applications_by_day' => $this->getApplicationsByDay($baseQuery),
            'top_companies' => $this->getTopCompanies($baseQuery)
        ];

        return response()->json($analytics);
    }

    /**
     * Calculate success rate
     */
    protected function calculateSuccessRate($query)
    {
        $total = $query->count();
        if ($total === 0) return 0;
        
        $successful = $query->where('status', 'submitted')->count();
        return round(($successful / $total) * 100, 1);
    }

    /**
     * Calculate average processing time
     */
    protected function calculateAverageProcessingTime($query)
    {
        $applications = $query->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as avg_seconds')
            ->first();

        return $applications->avg_seconds ? round($applications->avg_seconds) : 0;
    }

    /**
     * Get applications grouped by day
     */
    protected function getApplicationsByDay($query)
    {
        return $query->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date');
    }

    /**
     * Get top companies applied to
     */
    protected function getTopCompanies($query)
    {
        return $query->join('leads', 'job_applications.lead_id', '=', 'leads.id')
            ->selectRaw('leads.company, COUNT(*) as count')
            ->groupBy('leads.company')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->pluck('count', 'company');
    }
}