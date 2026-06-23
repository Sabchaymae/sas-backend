<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Http\Resources\IncidentResource;
use Illuminate\Http\Request;
use App\Jobs\DetectSimilarIncidentsJob;

class IncidentController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $query = Incident::with('creator');
        
        // If not admin, only show own incidents
        if (!($user->isAdmin() || $user->role === 'admin' || $user->role === 'administrateur')) {
            $query->where('created_by', $user->id);
        }
        
        $incidents = $query->orderBy('created_at', 'desc')->get();
        return IncidentResource::collection($incidents);
    }

    public function store(Request $request)
    {
        \Log::info('Incident creation request:', $request->all());

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'client_name' => 'required|string|max:255',
            'client_phone' => 'required|string|max:20',
            'client_address' => 'required|string',
            'city' => 'required|string',
            'client_email' => 'nullable|email',
            'incident_date' => 'required|date',
            'is_recurring' => 'boolean',
        ]);

        try {
            // Log for debugging
            \Log::info('Attempting to create incident...');

            // Correction orthographique automatique
            $client_name = ucwords(mb_strtolower(trim($validated['client_name'])));
            $client_address = ucfirst(trim($validated['client_address']));
            $client_email = !empty($validated['client_email']) ? mb_strtolower(trim($validated['client_email'])) : null;

            $incident = Incident::create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'client_name' => $client_name,
                'client_phone' => $validated['client_phone'],
                'client_address' => $client_address,
                'city' => $validated['city'],
                'client_email' => $client_email,
                'incident_date' => $validated['incident_date'],
                'is_recurring' => $validated['is_recurring'] ?? false,
                'created_by' => auth()->id(),
                'status' => 'OPEN'
            ]);

            // Lancer la détection de similarité en arrière-plan (Asynchrone)
            try {
                DetectSimilarIncidentsJob::dispatch($incident);
            } catch (\Exception $jobEx) {
                \Log::warning('Could not dispatch similarity job: ' . $jobEx->getMessage());
            }

            \Log::info('Incident created successfully:', ['id' => $incident->id]);

            return new IncidentResource($incident->load('creator'));
        } catch (\Exception $e) {
            \Log::error('Incident creation failed:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Erreur lors de la création de l\'incident',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $incident = Incident::with('creator')->findOrFail($id);
        
        $similarIncidentIds = \DB::table('incident_similarities')
            ->where('incident_id', $id)
            ->orWhere('similar_incident_id', $id)
            ->get()
            ->map(function($sim) use ($id) {
                return $sim->incident_id == $id ? $sim->similar_incident_id : $sim->incident_id;
            });

        $similarIncidents = Incident::whereIn('id', $similarIncidentIds)->get();

        return response()->json([
            'data' => new IncidentResource($incident),
            'similar_incidents' => IncidentResource::collection($similarIncidents)
        ]);
    }

    public function update(Request $request, $id)
    {
        $incident = Incident::findOrFail($id);
        $user = auth()->user();

        // Only allow admin or incident creator to update
        if (!($user->isAdmin() || $user->role === 'admin' || $user->role === 'administrateur' || $incident->created_by === $user->id)) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'client_name' => 'sometimes|required|string|max:255',
            'client_phone' => 'sometimes|required|string|max:20',
            'client_address' => 'sometimes|required|string',
            'city' => 'sometimes|required|string',
            'client_email' => 'nullable|email',
            'incident_date' => 'sometimes|required|date',
            'is_recurring' => 'boolean',
            'status' => 'sometimes|required|in:OPEN,IN_PROGRESS,RESOLVED,CLOSED',
        ]);

        // Correction orthographique si présent
        if (isset($validated['client_name'])) {
            $validated['client_name'] = ucwords(mb_strtolower(trim($validated['client_name'])));
        }
        if (isset($validated['client_address'])) {
            $validated['client_address'] = ucfirst(trim($validated['client_address']));
        }

        $incident->update($validated);

        return new IncidentResource($incident->load('creator'));
    }

    public function destroy($id)
    {
        $incident = Incident::findOrFail($id);
        $user = auth()->user();

        // Only allow admin or incident creator to delete
        if (!($user->isAdmin() || $user->role === 'admin' || $user->role === 'administrateur' || $incident->created_by === $user->id)) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        $incident->delete();
        return response()->json(['message' => 'Incident supprimé avec succès']);
    }
}
