<?php

namespace LaravelSpectrum\Tests\Fixtures\FormRequests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Fixture file containing anonymous class instances for testing AST-based analysis.
 */
class AnonymousClassFixture
{
    /**
     * Get an anonymous FormRequest with simple rules.
     */
    public static function getSimpleAnonymousRequest(): FormRequest
    {
        return new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'name' => 'required|string|max:255',
                    'email' => 'required|email',
                ];
            }

            public function attributes(): array
            {
                return [
                    'name' => 'User Name',
                    'email' => 'Email Address',
                ];
            }
        };
    }

    /**
     * Get an anonymous FormRequest with conditional rules.
     */
    public static function getConditionalAnonymousRequest(): FormRequest
    {
        return new class extends FormRequest
        {
            public function rules(): array
            {
                $rules = [
                    'name' => 'required|string',
                ];

                if ($this->type === 'premium') {
                    $rules['subscription_id'] = 'required|string';
                }

                return $rules;
            }
        };
    }

    /**
     * Get an anonymous FormRequest without any methods.
     */
    public static function getEmptyAnonymousRequest(): FormRequest
    {
        return new class extends FormRequest {};
    }
}
