<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'designation',
        'sku',
        'categorie',
        'fournisseur',
        'quantite',
        'seuil',
        'prix_unitaire',
    ];

    protected $casts = [
        'quantite' => 'integer',
        'seuil' => 'integer',
        'prix_unitaire' => 'decimal:2',
    ];

    protected static function booted()
    {
        static::creating(function ($product) {
            if (empty($product->sku)) {
                $product->sku = 'PRD-' . strtoupper(uniqid());
            }
            if (is_null($product->categorie)) {
                $product->categorie = '';
            }
            if (is_null($product->fournisseur)) {
                $product->fournisseur = '';
            }
        });
    }
}
