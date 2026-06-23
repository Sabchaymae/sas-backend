<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('dateNaissance')) {
            $this->merge([
                'date_naissance' => !empty($this->dateNaissance) ? $this->dateNaissance : null,
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:20', 'unique:users,telephone'],
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users,email'],
            'identifiant' => ['nullable', 'string', 'max:255', 'unique:users,identifiant'],
            'role' => ['required', 'string', 'max:100'],
            'statut' => ['nullable', 'string', 'in:active,inactive,pending'],
            'cin' => ['nullable', 'string', 'max:20'],
            'adresse' => ['nullable', 'string', 'max:500'],
            'date_naissance' => ['nullable', 'date', 'before:-18 years'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp,bmp,svg,tiff,tif,ico,heic,heif,avif', 'max:10240'],
        ];
    }
}
