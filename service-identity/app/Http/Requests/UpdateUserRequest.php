<?php

namespace App\Http\Requests;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
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
     */
    public function rules(): array
    {
        $userId = $this->route('user')->id;

        return [
            'nom' => ['sometimes', 'required', 'string', 'max:255'],
            'prenom' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', 'unique:users,email,' . $userId],
            'password' => ['sometimes', 'nullable', Password::defaults()],
            'telephone' => ['nullable', 'string', 'max:20'],
            'role' => ['sometimes', 'required', 'string', 'max:100'],
            'statut' => ['sometimes', 'nullable', new Enum(UserStatus::class)],
            'cin' => ['nullable', 'string', 'max:20'],
            'adresse' => ['nullable', 'string', 'max:500'],
            'date_naissance' => ['nullable', 'date'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp,bmp,svg,tiff,tif,ico,heic,heif,avif', 'max:10240'],
        ];
    }
}
