<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchedulerRun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SchedulerStatsController extends Controller
{
    /**
     * Get scheduler runs with pagination and filters
     */
    public function index(Request $request)
    {
        // Check if user is super admin
        if (!Auth::check() || !Auth::user()->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $perPage = $request->get('per_page', 20);
        $status = $request->get('status');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $searchTerm = $request->get('search_term');

        $query = SchedulerRun::query()->orderBy('created_at', 'desc');

        // Apply filters
        if ($status) {
            $query->where('status', $status);
        }

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        if ($searchTerm) {
            $query->where('search_term_used', 'like', "%{$searchTerm}%");
        }

        $runs = $query->paginate($perPage);

        return response()->json($runs);
    }

    /**
     * Get scheduler stats summary
     */
    public function summary()
    {
        // Check if user is super admin
        if (!Auth::check() || !Auth::user()->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $stats = [
            'total_runs' => SchedulerRun::count(),
            'successful_runs' => SchedulerRun::where('status', 'success')->count(),
            'failed_runs' => SchedulerRun::where('status', 'failed')->count(),
            'rate_limited_runs' => SchedulerRun::where('status', 'rate_limited')->count(),
            'total_jobs_collected' => SchedulerRun::sum('jobs_collected'),
            'total_duplicates_skipped' => SchedulerRun::sum('duplicates_skipped'),
            'average_jobs_per_run' => round(SchedulerRun::avg('jobs_collected'), 2),
            'last_run' => SchedulerRun::latest()->first(),
            'last_24h_runs' => SchedulerRun::where('created_at', '>=', now()->subDay())->count(),
            'last_24h_jobs_collected' => SchedulerRun::where('created_at', '>=', now()->subDay())->sum('jobs_collected'),
        ];

        return response()->json($stats);
    }
}
