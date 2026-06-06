<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityLogController extends Controller
{
    /**
     * GET /api/v1/history
     *
     * Returns paginated + filtered activity logs.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type'      => 'nullable|string',
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to'   => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'per_page'  => 'nullable|integer|min:1|max:100',
            'sort_by'   => 'nullable|string|in:created_at,type,module,user_name',
            'sort_dir'  => 'nullable|string|in:asc,desc',
        ]);

        $query = ActivityLog::query();

        // Note: Authorizations removed for development convenience (Public routes)

        $perPage = (int) $request->input('per_page', 10);
        $sortBy  = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');

        $logs = $query->search($request->input('search'))
            ->ofUser($request->input('user'))
            ->ofType($request->input('type'))
            ->ofModule($request->input('module'))
            ->fromDate($request->input('date_from'))
            ->toDate($request->input('date_to'))
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage);

        return ActivityLogResource::collection($logs)->response();
    }

    /**
     * GET /api/v1/history/{id}
     */
    public function show($id): JsonResponse
    {
        $log = ActivityLog::findOrFail($id);
        return (new ActivityLogResource($log))->response();
    }

    /**
     * GET /api/v1/history/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $total         = ActivityLog::count();
        $connexions    = ActivityLog::where('type', ActivityLog::TYPE_CONNEXION)->count();
        $creations     = ActivityLog::where('type', ActivityLog::TYPE_CREATION)->count();
        $anomalies     = ActivityLog::whereIn('type', [ActivityLog::TYPE_ANOMALIE, ActivityLog::TYPE_REFUS])->count();

        return response()->json([
            'total_actions'   => $total,
            'connexions'      => $connexions,
            'creations'       => $creations,
            'security_alerts' => $anomalies,
            'trends'          => [10, 15, 8, 20, 12, 18, 14] // Sample trends for UI
        ]);
    }

    /**
     * GET /api/v1/history/filters
     */
    public function filters(): JsonResponse
    {
        $users   = ActivityLog::select('user_name')->distinct()->orderBy('user_name')->pluck('user_name');
        $modules = ActivityLog::select('module')->distinct()->orderBy('module')->pluck('module');
        $types   = ActivityLog::ALL_TYPES;

        return response()->json([
            'users'   => $users,
            'modules' => $modules,
            'actions' => $types,
        ]);
    }

    /**
     * GET /api/v1/history/export
     *
     * Exports filtered logs as a CSV file.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = ActivityLog::query();

        $logs = $query->search($request->input('search'))
            ->ofUser($request->input('user'))
            ->ofType($request->input('type'))
            ->ofModule($request->input('module'))
            ->fromDate($request->input('date_from'))
            ->toDate($request->input('date_to'))
            ->orderBy('created_at', 'desc')
            ->get();

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="oriotel_historique_' . now()->format('Ymd_His') . '.csv"',
        ];

        $callback = function () use ($logs) {
            $file = fopen('php://output', 'w');
            
            // BOM for Excel UTF-8 compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Header Row
            fputcsv($file, ['ID', 'Utilisateur', 'Role', 'Type', 'Action', 'Module', 'Date', 'IP']);

            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->id,
                    $log->user_name,
                    $log->user_role,
                    $log->type,
                    $log->action,
                    $log->module,
                    $log->created_at->format('Y-m-d H:i:s'),
                    $log->ip_address ?? '-',
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * DELETE /api/v1/history/purge
     */
    public function purge(Request $request): JsonResponse
    {
        $days = (int) $request->input('days', 30);
        $deleted = ActivityLog::where('created_at', '<', now()->subDays($days))->delete();

        return response()->json([
            'success' => true,
            'message' => "$deleted logs plus anciens que $days jours ont été supprimés.",
        ]);
    }
}
