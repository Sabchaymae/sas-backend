<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        // Get selected date (default to today)
        $date = $request->has('date') ? Carbon::parse($request->date) : Carbon::today();
        
        // Get all users (excluding soft-deleted ones)
        $users = User::query()->whereNull('deleted_at');
        
        if ($request->has('user_id')) {
            $users->where('id', $request->user_id);
        }
        
        $users = $users->get();
        
        // Get all attendances for the selected date
        $attendancesByUserId = Attendance::whereDate('date', $date)
            ->get()
            ->keyBy('user_id');
            
        // Build the response data
        $data = $users->map(function ($user) use ($date, $attendancesByUserId) {
            // Get attendance for this user (if exists)
            $attendance = $attendancesByUserId->get($user->id);
            
            if (!$attendance) {
                // Create a mock attendance record if none exists
                $attendance = new Attendance([
                    'user_id' => $user->id,
                    'date' => $date->toDateString(),
                    'clock_in' => null,
                    'clock_out' => null,
                    'total_hours' => 0,
                    'overtime_hours' => 0,
                    'status' => 'Absent'
                ]);
                $attendance->id = null; // No ID since it's not in DB
            }
            
            // Prepare employee object with all required fields for frontend
            $employeeObj = [
                'id' => $user->id,
                'identifiant' => $user->identifiant,
                'nom' => $user->nom,
                'prenom' => $user->prenom,
                'name' => $user->name, // Uses accessor for full name
                'role' => $user->role,
                'department' => $user->role, // Alias for role
                'email' => $user->email,
                'cin' => $user->cin,
                'phone' => $user->phone,
            ];
            
            // Attach user and employee to attendance
            $attendance->user = $employeeObj;
            $attendance->employee = $employeeObj;
            
            // Récupérer tous les logs de la journée pour cet utilisateur
            $logs = AttendanceLog::where('user_id', $user->identifiant)
                ->whereDate('timestamp', $date)
                ->orderBy('timestamp', 'asc')
                ->get();

            $attendance->logs = $logs;

            // Déterminer la première entrée et la dernière sortie
            $attendance->first_clock_in = $logs->first()?->timestamp;
            $attendance->last_clock_out = $logs->last()?->timestamp;

            // Règles métier : Entrée à 10h max
            $isLate = false;
            if ($attendance->first_clock_in) {
                $entryTime = $attendance->first_clock_in->format('H:i:s');
                if ($entryTime > '10:00:00') {
                    $isLate = true;
                    $attendance->status = 'Late';
                }
            }

            $attendance->is_late = $isLate;
            
            return $attendance;
        });
        
        // Paginate the results (since we have a collection, we need to manually paginate)
        $perPage = 20;
        $page = $request->input('page', 1);
        $paginatedData = new \Illuminate\Pagination\LengthAwarePaginator(
            $data->forPage($page, $perPage),
            $data->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json($paginatedData);
    }

    /**
     * Store a new attendance log (called by Python microservice)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|string', // identifiant (matricule) from users table
            'timestamp' => 'required|date',
            'device_id' => 'required|integer',
        ]);

        // 1. Log the raw attendance
        $log = AttendanceLog::create([
            'user_id' => $validated['user_id'],
            'device_id' => $validated['device_id'],
            'timestamp' => $validated['timestamp'],
            'type' => 0, 
        ]);

        // 2. Process attendance
        $user = User::where('identifiant', $validated['user_id'])->first();
        if (!$user) {
            return response()->json(['error' => 'User not found with identifiant: ' . $validated['user_id']], 404);
        }

        $timestamp = Carbon::parse($validated['timestamp']);
        $date = $timestamp->toDateString();
        $time = $timestamp->toTimeString();

        $attendance = Attendance::firstOrCreate(
            ['user_id' => $user->id, 'date' => $date],
            ['clock_in' => $time, 'status' => 'Present']
        );

        if (!$attendance->wasRecentlyCreated) {
            // Update clock_out if the new time is later than existing clock_in
            if ($timestamp->format('H:i:s') > $attendance->clock_in) {
                $attendance->clock_out = $time;
                
                // Calculate total hours (use the same date for both times)
                $in = Carbon::parse($date . ' ' . $attendance->clock_in);
                $out = Carbon::parse($date . ' ' . $time);
                $attendance->total_hours = round($out->diffInMinutes($in) / 60, 2);
                
                $attendance->save();
            }
        }

        // 3. Trigger AI Analysis (Optional: async with timeout)
        try {
            Http::timeout(2)->post(config('services.ai_similarity.url') . '/analyze-attendance', [
                'attendance_id' => $attendance->id,
                'user_id' => $user->id,
                'date' => $date,
                'clock_in' => $attendance->clock_in,
                'clock_out' => $attendance->clock_out,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to trigger AI analysis: ' . $e->getMessage());
        }

        // Prepare employee object for response
        $employeeObj = [
            'id' => $user->id,
            'identifiant' => $user->identifiant,
            'nom' => $user->nom,
            'prenom' => $user->prenom,
            'name' => $user->name,
            'role' => $user->role,
            'department' => $user->role,
        ];
        $attendance->user = $employeeObj;
        $attendance->employee = $employeeObj;

        return response()->json([
            'message' => 'Attendance logged and processed',
            'attendance' => $attendance
        ]);
    }
}
