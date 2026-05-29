<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'code'  => ['required', 'string', 'size:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.email'    => 'L\'adresse email est invalide.',
            'code.required'  => 'Le code de vérification est obligatoire.',
            'code.size'      => 'Le code doit contenir exactement 6 chiffres.',
        ];
    }
}
