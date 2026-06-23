<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmployeeController extends Controller
{
    public function index()
    {
        $users = User::whereNull('deleted_at')->get()->map(function ($user) {
            // Add name field (full name) and department (role alias)
            $userArray = $user->toArray();
            $userArray['name'] = $user->name;
            $userArray['department'] = $user->role;
            $userArray['matricule'] = $user->identifiant;
            return $userArray;
        });

        return response()->json($users);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'identifiant' => 'required|string|unique:users',
            'nom' => 'required|string',
            'prenom' => 'required|string',
            'email' => 'required|string|unique:users',
            'role' => 'nullable|string',
            'department' => 'nullable|string',
        ]);

        // Create user with default password (you might want to adjust this)
        $validated['password'] = bcrypt('password');
        $validated['statut'] = 'active';
        $user = User::create($validated);

        // Sync with ZKTeco terminal via Python microservice
        try {
            $response = Http::post(config('services.ai_similarity.url') . '/sync-user', [
                'user_id' => $user->identifiant,
                'name' => $user->name,
                'department' => $request->department ?? $user->role,
            ]);

            if (!$response->successful()) {
                Log::error('Failed to sync user with ZKTeco: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('Error connecting to AI service: ' . $e->getMessage());
        }

        // Return user with extra fields for backward compatibility
        $userArray = $user->toArray();
        $userArray['name'] = $user->name;
        $userArray['department'] = $user->role;
        $userArray['matricule'] = $user->identifiant;

        return response()->json($userArray, 201);
    }

    public function show($id)
    {
        $user = User::findOrFail($id);
        $userArray = $user->toArray();
        $userArray['name'] = $user->name;
        $userArray['department'] = $user->role;
        $userArray['matricule'] = $user->identifiant;
        return response()->json($userArray);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        
        $validated = $request->validate([
            'identifiant' => 'string|unique:users,identifiant,' . $user->id,
            'nom' => 'string',
            'prenom' => 'string',
            'email' => 'string|unique:users,email,' . $user->id,
            'role' => 'nullable|string',
            'department' => 'nullable|string',
        ]);

        $user->update($validated);

        $userArray = $user->toArray();
        $userArray['name'] = $user->name;
        $userArray['department'] = $user->role;
        $userArray['matricule'] = $user->identifiant;

        return response()->json($userArray);
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        return response()->json(null, 204);
    }
}
