<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'email' => $this->email,
            'telephone' => $this->telephone,
            'role' => $this->role,
            'statut' => $this->statut,
            'cin' => $this->cin,
            'adresse' => $this->adresse,
            'date_naissance' => $this->date_naissance ? $this->date_naissance->format('Y-m-d') : null,
            'photo' => $this->photo ? Storage::disk('public')->url($this->photo) : null,
            'avatar' => $this->avatar,
            'name' => $this->name,
            'identifiant' => $this->identifiant ?? ('USR-' . str_pad($this->id, 5, '0', STR_PAD_LEFT)),
            'date_creation' => $this->created_at->format('d/m/Y H:i'),
        ];
    }
}
