<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class HistoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = ActivityLog::with('user');

        // Filtering
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->has('module')) {
            $query->where('module', $request->input('module'));
        }
        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%')
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', '%' . $search . '%');
                  });
            });
        }

        // Sorting
        $sortField = $request->input('sort_field', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortField, $sortDir);

        // Pagination
        $perPage = $request->input('per_page', 10);
        $activityLogs = $query->paginate($perPage);

        return response()->json($activityLogs);
    }

    /**
     * Get activity statistics.
     */
    public function stats()
    {
        $totalActions = ActivityLog::count();
        $connexions = ActivityLog::where('type', 'login')->count();
        $creations = ActivityLog::where('type', 'create')->count();
        $securityAlerts = ActivityLog::where('type', 'security_alert')->count();

        return response()->json([
            'total_actions' => $totalActions,
            'connexions' => $connexions,
            'creations' => $creations,
            'security_alerts' => $securityAlerts,
        ]);
    }

    /**
     * Get available filters for activity logs.
     */
    public function getFilters()
    {
        $types = ActivityLog::select('type')->distinct()->pluck('type');
        $modules = ActivityLog::select('module')->distinct()->pluck('module');

        return response()->json([
            'types' => $types,
            'modules' => $modules,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(ActivityLog $activityLog)
    {
        return response()->json($activityLog->load('user'));
    }
}