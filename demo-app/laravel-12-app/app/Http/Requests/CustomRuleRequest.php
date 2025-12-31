<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\StrongPassword;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest testing custom validation rules.
 */
class CustomRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Custom Rule class implementing ValidationRule
            'password' => ['required', new StrongPassword(minLength: 16)],

            // Closure-based validation rule
            'username' => [
                'required',
                'string',
                'min:3',
                'max:20',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (str_starts_with($value, 'admin')) {
                        $fail("The {$attribute} cannot start with 'admin'.");
                    }
                },
            ],

            // Rule with regex pattern
            'phone' => [
                'nullable',
                'string',
                'regex:/^\+?[1-9]\d{1,14}$/',
            ],

            // Multiple rules including exists
            'category_id' => [
                'required',
                'integer',
                'exists:categories,id',
            ],

            // URL validation
            'website' => ['nullable', 'url:http,https'],

            // Active URL (actually checks DNS)
            'callback_url' => ['nullable', 'active_url'],

            // Timezone validation
            'timezone' => ['nullable', 'timezone:all'],

            // Accept specific values using accepted_if
            'terms' => ['accepted'],
            'marketing' => ['nullable', 'boolean'],

            // Prohibits field if another field is present
            'old_password' => ['nullable', 'string'],
            'new_password' => [
                'prohibited_unless:old_password,null',
                'required_with:old_password',
                'string',
                'different:old_password',
            ],

            // Required array keys
            'config' => ['nullable', 'array', 'required_array_keys:host,port'],
            'config.host' => ['required_with:config', 'string'],
            'config.port' => ['required_with:config', 'integer', 'between:1,65535'],
        ];
    }
}
