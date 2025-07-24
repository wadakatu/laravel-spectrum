<?php

namespace LaravelSpectrum\Tests\Fixtures\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ArrayValidationRequest extends FormRequest
{
    public function rules(): array
    {
        if ($this->isMethod('POST')) {
            return [
                'tags' => ['required', 'array'],
                'tags.*' => ['string', 'max:50'],
            ];
        }

        return [
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string'],
        ];
    }
}
