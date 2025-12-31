<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest testing 'sometimes' and conditional validation patterns.
 */
class SometimesValidationRequest extends FormRequest
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
            // Sometimes - only validate if present
            'nickname' => ['sometimes', 'string', 'max:50'],

            // Exclude rules
            'internal_notes' => ['exclude'],  // Never included in validated data
            'debug_info' => ['exclude_if:environment,production'],
            'legacy_field' => ['exclude_unless:include_legacy,true'],

            // Multiple conditional rules
            'shipping_address' => [
                'required_without:pickup_location',
                'nullable',
                'string',
                'max:255',
            ],
            'pickup_location' => [
                'required_without:shipping_address',
                'nullable',
                'string',
                'max:100',
            ],

            // Required with multiple fields
            'confirmation_code' => [
                'required_with_all:email,phone',
                'nullable',
                'string',
                'size:6',
            ],

            // Missing rules (Laravel 10+)
            'optional_field' => ['missing_if:is_guest,true'],
            'guest_email' => ['missing_unless:is_guest,true'],
            'either_field' => ['missing_with:alternative_field'],
            'alternative_field' => ['missing_with:either_field'],

            // Same validation
            'password' => ['required', 'string', 'min:8'],
            'password_confirmation' => ['required', 'same:password'],

            // Different validation
            'new_email' => ['required', 'email'],
            'old_email' => ['required', 'email', 'different:new_email'],

            // Confirmed (expects _confirmation field)
            'secret' => ['required', 'string', 'confirmed'],

            // Complex conditional with multiple options
            'payment_type' => ['required', 'in:card,bank,crypto'],
            'card_number' => [
                'required_if:payment_type,card',
                'nullable',
                'string',
                'size:16',
            ],
            'bank_account' => [
                'required_if:payment_type,bank',
                'nullable',
                'string',
            ],
            'wallet_address' => [
                'required_if:payment_type,crypto',
                'nullable',
                'string',
            ],
        ];
    }
}
