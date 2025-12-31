<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Rules\StrongPassword;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

/**
 * Advanced FormRequest testing various production patterns.
 */
class AdvancedFormRequest extends FormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Normalize email to lowercase
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower($this->email),
            ]);
        }

        // Set default values
        $this->merge([
            'locale' => $this->locale ?? 'en',
            'timezone' => $this->timezone ?? 'UTC',
        ]);
    }

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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Basic string with Rule object
            'name' => ['required', 'string', 'max:255'],

            // Email with unique check (excluding current user on update)
            'email' => [
                'required',
                'email',
                Rule::unique('users')->ignore($this->route('user')),
            ],

            // Password with Password rule object
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
            ],

            // Enum validation using Enum rule
            'role' => ['required', new Enum(UserRole::class)],
            'status' => ['nullable', new Enum(UserStatus::class)],

            // Rule::in with array
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],

            // Conditional validation with Rule::when
            'company_name' => [
                Rule::when($this->role === 'business', ['required', 'string', 'max:255']),
            ],

            // Rule::requiredIf with closure
            'tax_id' => [
                Rule::requiredIf(fn () => $this->role === 'business'),
                'nullable',
                'string',
                'max:50',
            ],

            // Rule::prohibitedIf
            'personal_id' => [
                Rule::prohibitedIf($this->role === 'business'),
                'nullable',
                'string',
            ],

            // Dimensions rule for images
            'avatar' => [
                'nullable',
                'image',
                Rule::dimensions()
                    ->minWidth(100)
                    ->minHeight(100)
                    ->maxWidth(2000)
                    ->maxHeight(2000)
                    ->ratio(1),
            ],

            // File rule
            'resume' => [
                'nullable',
                'file',
                'mimes:pdf,doc,docx',
                'max:10240',
            ],

            // Nested object with Rule::forEach
            'addresses' => ['nullable', 'array', 'max:5'],
            'addresses.*.type' => ['required', Rule::in(['home', 'work', 'billing', 'shipping'])],
            'addresses.*.street' => ['required', 'string', 'max:255'],
            'addresses.*.city' => ['required', 'string', 'max:100'],
            'addresses.*.postal_code' => ['required', 'string', 'max:20'],
            'addresses.*.country' => ['required', 'string', 'size:2'],
            'addresses.*.is_default' => ['nullable', 'boolean'],

            // Date constraints
            'birth_date' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['nullable', 'date', 'after:start_date'],

            // Numeric ranges
            'age' => ['nullable', 'integer', 'between:18,120'],
            'salary' => ['nullable', 'numeric', 'min:0', 'max:10000000'],

            // Array with distinct values
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'max:50', 'distinct:ignore_case'],

            // JSON field
            'metadata' => ['nullable', 'json'],

            // Custom validation rule object
            // 'secret' => ['nullable', new StrongPassword],
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
            'tax_id' => 'Tax Identification Number',
            'addresses.*.postal_code' => 'postal code',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.uncompromised' => 'This password has been compromised in a data breach.',
            'avatar.dimensions' => 'Avatar must be square (1:1 ratio) between 100x100 and 2000x2000 pixels.',
        ];
    }

    /**
     * Handle a passed validation attempt.
     */
    protected function passedValidation(): void
    {
        // Additional processing after validation passes
        // e.g., logging, metrics
    }
}
