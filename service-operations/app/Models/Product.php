<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'designation',
        'sku',
        'categorie',
        'fournisseur',
        'quantite',
        'seuil',
        'prix_unitaire'
    ];

    protected $casts = [
        'quantite' => 'integer',
        'seuil' => 'integer',
        'prix_unitaire' => 'float',
    ];
}
