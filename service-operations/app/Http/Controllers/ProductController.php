<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\LowStockAlert;
use Illuminate\Support\Facades\Notification;

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
        \Log::info('Store product request received', $request->all());
        
        $validated = $request->validate([
            'designation' => 'required|string|max:255',
            'sku' => 'nullable|string|max:255',
            'categorie' => 'nullable|string|max:255',
            'fournisseur' => 'nullable|string|max:255',
            'quantite' => 'nullable|integer',
            'seuil' => 'nullable|integer',
            'prix_unitaire' => 'nullable|numeric',
        ]);

        try {
            $product = \App\Models\Product::create($validated);
            \Log::info('Product created successfully', ['id' => $product->id]);

            return response()->json([
                'success' => true,
                'product' => $product
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Product creation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
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
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully'
        ]);
    }

    /**
     * Gère les mouvements de stock (Entrée/Sortie) avec traçabilité.
     */
    public function handleMovement(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'type' => 'required|in:entrée,sortie',
            'quantite' => 'required|integer|min:1',
            'motif' => 'required|string|max:255',
        ]);

        $type = $validated['type'];
        $quantite = $validated['quantite'];

        try {
            return DB::transaction(function () use ($product, $type, $quantite, $validated, $request) {
                // 2. Mise à jour de la quantité du produit
                if ($type === 'entrée') {
                    $product->quantite += $quantite;
                } else {
                    $product->quantite -= $quantite;
                }
                $product->save();

                // 3. Historisation du mouvement
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => $type,
                    'quantite' => $quantite,
                    'motif' => $validated['motif'],
                    'user_id' => $request->user()?->id,
                ]);

                // Automatisation PFE : Alerte anticipée à Seuil + 1
                $triggerAlert = $product->quantite <= ($product->seuil + 1);

                // 4. Automatisation : Déclencheur de Réapprovisionnement & Notification Admin
                if ($triggerAlert) {
                    $this->triggerReorderAutomation($product);
                }

                return response()->json([
                    'success' => true,
                    'product' => $product,
                    'trigger_alert' => $triggerAlert,
                    'message' => $triggerAlert 
                        ? "Alerte : Le stock de {$product->designation} est critique (Seuil+1 atteint). Demande d'achat envoyée à l'Admin."
                        : 'Mouvement de stock enregistré avec succès.'
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement du mouvement : ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Simule l'automatisation : Notification Admin, Email et Génération de PDF (Brouillon)
     */
    public function triggerReorderAutomation($product)
    {
        // 1. Récupérer dynamiquement tous les administrateurs
        $admins = User::where('role', 'admin')->get();

        if ($admins->isEmpty()) {
            \Log::warning("ALERTE AUTOMATISATION : Aucun administrateur trouvé en base de données.");
            return;
        }

        // 2. ENVOI RÉEL des Notifications (Database + Mail via SMTP)
        Notification::send($admins, new LowStockAlert($product));

        \Log::info("NOTIFICATIONS RÉELLES ENVOYÉES : " . $admins->count() . " admins notifiés pour {$product->designation}.");
        
        // 3. Simulation Génération Bon de Commande PDF
        $poDetails = [
            'fournisseur' => $product->fournisseur,
            'produit' => $product->designation,
            'quantite_recommandee' => ($product->seuil * 2),
            'date_generation' => now()->format('d/m/Y H:i')
        ];
        
        \Log::info("PDF GÉNÉRÉ (Brouillon) : Bon de Commande pour {$product->fournisseur} créé avec succès.", $poDetails);
    }

    /**
     * Récupère les notifications non lues pour l'admin.
     */
    public function getNotifications(Request $request)
    {
        // Pour le PFE, on récupère les notifications du premier admin trouvé ou de l'user connecté
        $user = $request->user() ?: User::where('role', 'admin')->first();
        
        if (!$user) return response()->json(['success' => false, 'notifications' => []]);

        return response()->json([
            'success' => true,
            'notifications' => $user->unreadNotifications,
            'unread_count' => $user->unreadNotifications->count()
        ]);
    }

    /**
     * Marque une notification comme lue.
     */
    public function markNotificationAsRead(Request $request, $id)
    {
        $user = $request->user() ?: User::where('role', 'admin')->first();
        if ($user) {
            $notification = $user->notifications()->findOrFail($id);
            $notification->markAsRead();
        }
        return response()->json(['success' => true]);
    }

    /**
     * Récupère l'historique complet des mouvements de stock.
     */
    public function movements()
    {
        $movements = StockMovement::with('product')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'movements' => $movements
        ]);
    }
}
