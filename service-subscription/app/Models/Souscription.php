<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Souscription extends Model
{
    protected $fillable = [
        'client_nom',
        'client_prenom',
        'client_cin',
        'client_telephone',
        'date_naissance',
        'adresse',
        'operateur',
        'type_objectif',
        'sous_type',
        'statut',
        'date_soumission',
        'motif_refus',
        'agent_id',
        'agence_id',
        'numero_ligne_fixe',
        'contrat_path',
        'cin_recto_path',
        'cin_verso_path',
    ];
}
