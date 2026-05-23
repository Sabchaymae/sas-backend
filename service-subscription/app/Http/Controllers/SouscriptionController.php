<?php

namespace App\Http\Controllers;

use App\Models\Souscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SouscriptionController extends Controller
{
    /**
     * Display a listing of the resource.
     * Assistant uses this to fetch dossiers to verify.
     */
    public function index(Request $request)
    {
        $query = Souscription::query();

        // Optional filter by status
        if ($request->has('statut')) {
            $query->where('statut', $request->statut);
        }

        $souscriptions = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $souscriptions
        ]);
    }

    /**
     * Store a newly created resource in storage.
     * Animateur uses this to submit a new subscription.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_nom'       => 'required|string|max:100',
            'client_prenom'    => 'required|string|max:100',
            'client_cin'       => 'required|string|max:20',
            'client_telephone' => 'nullable|string|max:20',
            'date_naissance'   => 'nullable|date',
            'adresse'          => 'nullable|string',
            'operateur'        => 'required|string|max:50',
            'type_objectif'    => 'required|string|max:50',
            'sous_type'        => 'nullable|string|max:50',
            'is_draft'         => 'nullable|boolean',
            'cin_recto'        => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
            'cin_verso'        => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
        ]);

        if ($duplicateMsg = $this->checkDuplicates($validated['client_cin'], $validated['adresse'] ?? null, $validated['operateur'], $validated['type_objectif'])) {
            return response()->json([
                'success' => false,
                'message' => $duplicateMsg
            ], 422);
        }

        $isDraft = $request->input('is_draft', false);

        $souscription = new Souscription($validated);
        $souscription->statut         = $isDraft ? 'Brouillon' : 'En attente';
        $souscription->date_soumission = now();

        // Store CIN images
        if ($request->hasFile('cin_recto')) {
            $souscription->cin_recto_path = $request->file('cin_recto')->store('cin_images', 'public');
        }
        if ($request->hasFile('cin_verso')) {
            $souscription->cin_verso_path = $request->file('cin_verso')->store('cin_images', 'public');
        }

        $souscription->save();

        return response()->json([
            'success' => true,
            'message' => $isDraft ? 'Brouillon sauvegardé.' : 'Dossier transmis pour vérification.',
            'data'    => $souscription
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $souscription = Souscription::findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $souscription
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $souscription = Souscription::findOrFail($id);

        $validated = $request->validate([
            'client_nom'       => 'required|string|max:100',
            'client_prenom'    => 'required|string|max:100',
            'client_cin'       => 'required|string|max:20',
            'client_telephone' => 'nullable|string|max:20',
            'date_naissance'   => 'nullable|date',
            'adresse'          => 'nullable|string',
            'operateur'        => 'required|string|max:50',
            'type_objectif'    => 'required|string|max:50',
            'sous_type'        => 'nullable|string|max:50',
            'is_draft'         => 'nullable|boolean',
            'cin_recto'        => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
            'cin_verso'        => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
        ]);

        if ($duplicateMsg = $this->checkDuplicates($validated['client_cin'], $validated['adresse'] ?? null, $validated['operateur'], $validated['type_objectif'], $id)) {
            return response()->json([
                'success' => false,
                'message' => $duplicateMsg
            ], 422);
        }

        $souscription->fill($validated);

        // Replace CIN images if new ones are uploaded
        if ($request->hasFile('cin_recto')) {
            if ($souscription->cin_recto_path) Storage::disk('public')->delete($souscription->cin_recto_path);
            $souscription->cin_recto_path = $request->file('cin_recto')->store('cin_images', 'public');
        }
        if ($request->hasFile('cin_verso')) {
            if ($souscription->cin_verso_path) Storage::disk('public')->delete($souscription->cin_verso_path);
            $souscription->cin_verso_path = $request->file('cin_verso')->store('cin_images', 'public');
        }

        $isDraft = $request->input('is_draft', false);
        if ($souscription->statut === 'Brouillon' || $souscription->statut === 'En attente') {
            $souscription->statut = $isDraft ? 'Brouillon' : 'En attente';
        }

        $souscription->save();

        return response()->json([
            'success' => true,
            'message' => 'Souscription mise à jour.',
            'data'    => $souscription
        ]);
    }

    /**
     * Update only the status of the specified resource.
     * Assistant uses this to validate or refuse a dossier.
     * On refusal, physical CIN images and contract are deleted.
     */
    public function updateStatus(Request $request, string $id)
    {
        $souscription = Souscription::findOrFail($id);

        $validated = $request->validate([
            'statut'            => 'required|string',
            'motif_refus'       => 'required_if:statut,Refusé|nullable|string',
            'numero_ligne_fixe' => 'nullable|string|max:50',
            'contrat'           => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240'
        ]);

        $souscription->statut = $validated['statut'];

        if ($validated['statut'] === 'Refusé') {
            if (isset($validated['motif_refus'])) {
                $souscription->motif_refus = $validated['motif_refus'];
            }
        }

        if (isset($validated['numero_ligne_fixe'])) {
            $souscription->numero_ligne_fixe = $validated['numero_ligne_fixe'];
        }

        if ($request->hasFile('contrat')) {
            if ($souscription->contrat_path) Storage::disk('public')->delete($souscription->contrat_path);
            $souscription->contrat_path = $request->file('contrat')->store('contrats', 'public');
        }

        $souscription->save();

        return response()->json([
            'success' => true,
            'message' => 'Statut mis à jour avec succès.',
            'data'    => $souscription
        ]);
    }

    /**
     * Remove the specified resource from storage.
     * Also deletes physical CIN images and contract file.
     */
    public function destroy(string $id)
    {
        $souscription = Souscription::findOrFail($id);

        // Delete all physical files
        $this->deleteCinImages($souscription);
        if ($souscription->contrat_path) {
            Storage::disk('public')->delete($souscription->contrat_path);
        }

        $souscription->delete();

        return response()->json([
            'success' => true,
            'message' => 'Souscription et fichiers associés supprimés.'
        ]);
    }

    /**
     * Delete physical CIN images for a subscription.
     */
    private function deleteCinImages(Souscription $souscription): void
    {
        if ($souscription->cin_recto_path) {
            Storage::disk('public')->delete($souscription->cin_recto_path);
            $souscription->cin_recto_path = null;
        }
        if ($souscription->cin_verso_path) {
            Storage::disk('public')->delete($souscription->cin_verso_path);
            $souscription->cin_verso_path = null;
        }
    }

    /**
     * Check for duplicate subscriptions.
     * Blocks same address + same operator + same type (even with different CIN).
     * Blocks same CIN + same operator + same type.
     */
    private function checkDuplicates($cin, $adresse, $operateur, $typeObjectif, $excludeId = null)
    {
        $query = Souscription::query();

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $existing = $query->get();

        foreach ($existing as $sub) {
            // Same address + same operator + same type (even different CIN = suspicious)
            if (!empty($adresse) && $sub->adresse === $adresse) {
                if ($sub->operateur === $operateur && $sub->type_objectif === $typeObjectif) {
                    return "Une souscription pour cette même adresse, avec le même opérateur et type d'offre existe déjà (doublon suspect).";
                }
            }

            // Same CIN + same operator + same type
            if ($sub->client_cin === $cin && $sub->operateur === $operateur && $sub->type_objectif === $typeObjectif) {
                return "Ce client a déjà une souscription identique en cours.";
            }
        }

        return false;
    }
}
