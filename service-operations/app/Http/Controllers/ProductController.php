<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'products' => \App\Models\Product::orderBy('id', 'desc')->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'designation' => 'required|string|max:255',
            'sku' => 'nullable|string|max:255',
            'categorie' => 'nullable|string|max:255',
            'fournisseur' => 'nullable|string|max:255',
            'quantite' => 'nullable|integer',
            'seuil' => 'nullable|integer',
            'prix_unitaire' => 'nullable|numeric',
        ]);

        $product = \App\Models\Product::create($validated);

        return response()->json([
            'success' => true,
            'product' => $product
        ], 201);
    }

    public function show($id)
    {
        $product = \App\Models\Product::findOrFail($id);
        
        return response()->json([
            'success' => true,
            'product' => $product
        ]);
    }

    public function update(Request $request, $id)
    {
        $product = \App\Models\Product::findOrFail($id);

        $validated = $request->validate([
            'designation' => 'sometimes|required|string|max:255',
            'sku' => 'nullable|string|max:255',
            'categorie' => 'nullable|string|max:255',
            'fournisseur' => 'nullable|string|max:255',
            'quantite' => 'nullable|integer',
            'seuil' => 'nullable|integer',
            'prix_unitaire' => 'nullable|numeric',
        ]);

        $product->update($validated);

        return response()->json([
            'success' => true,
            'product' => $product
        ]);
    }

    public function destroy($id)
    {
        $product = \App\Models\Product::findOrFail($id);
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully'
        ]);
    }
}
