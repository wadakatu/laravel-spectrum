<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConditionalUserRequest extends FormRequest
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
        $baseRules = $this->getBaseRules();

        if ($this->isMethod('POST')) {
            return array_merge($baseRules, [
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:8|confirmed',
                'role' => 'required|in:admin,user,moderator',
            ]);
        } elseif ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules = array_merge($baseRules, [
                'email' => 'sometimes|email|unique:users,email,'.$this->route('user'),
                'current_password' => Rule::requiredIf($this->has('password')),
            ]);

            if ($this->user() && $this->user()->isAdmin()) {
                $rules['role'] = 'sometimes|in:admin,user,moderator';
                $rules['permissions'] = 'array';
                $rules['permissions.*'] = 'string|exists:permissions,name';
            }

            return $rules;
        }

        return $baseRules;
    }

    /**
     * Get base validation rules
     */
    private function getBaseRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'bio' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => 'email address',
            'password' => 'password',
            'current_password' => 'current password',
        ];
    }
}
