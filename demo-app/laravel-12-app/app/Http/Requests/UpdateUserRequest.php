<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,'.$this->route('id'),
            'password' => 'sometimes|string|min:8|confirmed',
            'profile' => 'sometimes|array',
            'profile.bio' => 'nullable|string|max:500',
            'profile.avatar' => 'nullable|url',
            'profile.website' => 'nullable|url',
        ];
    }
}
