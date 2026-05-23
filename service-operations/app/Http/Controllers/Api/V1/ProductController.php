<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::query();

        // Simple search
        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('designation', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('fournisseur', 'like', "%{$search}%");
            });
        }

        // Category filter
        if ($request->has('categorie') && $request->query('categorie') !== 'Toutes catégories') {
            $query->where('categorie', $request->query('categorie'));
        }

        // Supplier filter
        if ($request->has('fournisseur') && $request->query('fournisseur') !== 'Tous fournisseurs') {
            $query->where('fournisseur', $request->query('fournisseur'));
        }

        // Alert filter
        if ($request->has('alert')) {
            $alert = $request->query('alert');
            if ($alert === 'yes') {
                $query->whereColumn('quantite', '<=', 'seuil');
            } elseif ($alert === 'no') {
                $query->whereColumn('quantite', '>', 'seuil');
            }
        }

        $products = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'products' => $products
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'designation' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100|unique:products,sku',
            'categorie' => 'required|string|max:255',
            'fournisseur' => 'required|string|max:255',
            'quantite' => 'required|integer|min:0',
            'seuil' => 'required|integer|min:0',
            'prix_unitaire' => 'required|numeric|min:0',
        ]);

        if (empty($validated['sku'])) {
            $validated['sku'] = 'PRD-' . strtoupper(Str::random(6)) . '-' . time();
        }

        $product = Product::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Produit créé avec succès.',
            'product' => $product
        ], 210); // Or 201 Created
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'success' => true,
            'product' => $product
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'designation' => 'sometimes|required|string|max:255',
            'sku' => 'sometimes|required|string|max:100|unique:products,sku,' . $product->id,
            'categorie' => 'sometimes|required|string|max:255',
            'fournisseur' => 'sometimes|required|string|max:255',
            'quantite' => 'sometimes|required|integer|min:0',
            'seuil' => 'sometimes|required|integer|min:0',
            'prix_unitaire' => 'sometimes|required|numeric|min:0',
        ]);

        $product->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Produit mis à jour avec succès.',
            'product' => $product
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Produit supprimé avec succès.'
        ]);
    }
}
