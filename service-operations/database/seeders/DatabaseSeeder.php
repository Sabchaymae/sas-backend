<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        \App\Models\Product::create([
            'designation' => 'Draps Coton King Size',
            'sku' => 'DR-KS-001',
            'categorie' => 'Linge de lit',
            'fournisseur' => 'Textiles & Co',
            'quantite' => 8,
            'seuil' => 20,
            'prix_unitaire' => 35.00,
        ]);

        \App\Models\Product::create([
            'designation' => 'Nettoyant Multi-surfaces',
            'sku' => 'CH-MS-045',
            'categorie' => 'Produits ménagers',
            'fournisseur' => 'CleanPro',
            'quantite' => 142,
            'seuil' => 30,
            'prix_unitaire' => 4.50,
        ]);

        \App\Models\Product::create([
            'designation' => 'Serviettes Bain 50×100',
            'sku' => 'DR-SB-002',
            'categorie' => 'Salle de bain',
            'fournisseur' => 'FreshLinen',
            'quantite' => 85,
            'seuil' => 25,
            'prix_unitaire' => 12.00,
        ]);

        \App\Models\Product::create([
            'designation' => 'Savon Liquide 500ml',
            'sku' => 'SB-SL-010',
            'categorie' => 'Salle de bain',
            'fournisseur' => 'CleanPro',
            'quantite' => 5,
            'seuil' => 50,
            'prix_unitaire' => 3.20,
        ]);

        \App\Models\Product::create([
            'designation' => 'Oreillers Plume Standard',
            'sku' => 'LB-OP-008',
            'categorie' => 'Linge de lit',
            'fournisseur' => 'FreshLinen',
            'quantite' => 200,
            'seuil' => 40,
            'prix_unitaire' => 22.00,
        ]);

        \App\Models\Product::create([
            'designation' => 'Détergent Lessive 5L',
            'sku' => 'PM-DL-015',
            'categorie' => 'Produits ménagers',
            'fournisseur' => 'CleanPro',
            'quantite' => 18,
            'seuil' => 20,
            'prix_unitaire' => 15.50,
        ]);
    }
}
