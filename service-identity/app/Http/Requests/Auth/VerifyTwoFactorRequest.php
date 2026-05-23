<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'code'    => ['required', 'string', 'size:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'L\'identifiant utilisateur est obligatoire.',
            'user_id.exists'   => 'Utilisateur introuvable.',
            'code.required'    => 'Le code de vérification est obligatoire.',
            'code.size'        => 'Le code doit contenir exactement 6 chiffres.',
        ];
    }
}
