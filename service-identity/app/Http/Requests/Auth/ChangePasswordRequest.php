<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required_without:token', 'string'],
            'password'         => [
                'required',
                'string',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required_without' => 'Le mot de passe actuel est obligatoire.',
            'password.required'                 => 'Le nouveau mot de passe est obligatoire.',
            'password.confirmed'                => 'La confirmation du mot de passe ne correspond pas.',
        ];
    }
}
