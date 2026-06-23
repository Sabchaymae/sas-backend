<?php

namespace App\Http\Controllers;

use App\Models\AttendanceAnomaly;
use Illuminate\Http\Request;

class AttendanceAnomalyController extends Controller
{
    public function index(Request $request)
    {
        $query = AttendanceAnomaly::with('user');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('severity')) {
            $query->where('severity', $request->severity);
        }

        $anomalies = $query->paginate(20);
        
        // Add employee alias for backward compatibility
        $anomalies->getCollection()->transform(function ($anomaly) {
            $anomaly->employee = $anomaly->user;
            return $anomaly;
        });

        return response()->json($anomalies);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required',
            'date' => 'required|date',
            'type' => 'required|string',
            'description' => 'nullable|string',
            'severity' => 'required|string|in:low,medium,high',
            'status' => 'required|string|in:pending,resolved,ignored',
        ]);

        $anomaly = AttendanceAnomaly::create($validated);

        return response()->json($anomaly, 201);
    }

    public function update(Request $request, AttendanceAnomaly $attendanceAnomaly)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:pending,resolved,ignored',
            'description' => 'nullable|string',
        ]);

        $attendanceAnomaly->update($validated);

        return response()->json($attendanceAnomaly);
    }
}
