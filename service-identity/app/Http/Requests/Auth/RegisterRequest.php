<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name'    => ['required', 'string', 'max:100'],
            'last_name'     => ['required', 'string', 'max:100'],
            'email'         => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'cin'           => ['required', 'string', 'max:50', 'unique:users,cin'],
            'role'          => ['required', 'string', \Illuminate\Validation\Rule::in(User::REGISTERABLE_ROLES)],
            'phone'         => ['nullable', 'string', 'max:20'],
            'password'      => [
                'required',
                'string',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
            'access_reason' => ['nullable', 'string', 'max:500'],
            'terms'         => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required'    => 'Le prénom est obligatoire.',
            'last_name.required'     => 'Le nom est obligatoire.',
            'email.required'         => 'L\'adresse email est obligatoire.',
            'email.unique'           => 'Cette adresse email est déjà utilisée.',
            'cin.required'           => 'Le CIN (ou identifiant) est obligatoire.',
            'cin.unique'             => 'Ce CIN est déjà utilisé.',
            'role.required'          => 'Le rôle est obligatoire.',
            'role.in'                => 'Le rôle sélectionné n\'est pas valide.',
            'password.required'      => 'Le mot de passe est obligatoire.',
            'password.confirmed'     => 'La confirmation du mot de passe ne correspond pas.',
            'access_reason.required' => 'La raison de la demande d\'accès est obligatoire.',
            'terms.accepted'         => 'Vous devez accepter les conditions d\'utilisation.',
        ];
    }
}
