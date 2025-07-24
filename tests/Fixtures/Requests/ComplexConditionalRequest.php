<?php

namespace LaravelSpectrum\Tests\Fixtures\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ComplexConditionalRequest extends FormRequest
{
    public function rules(): array
    {
        if ($this->user() && $this->user()->isPremium()) {
            return [
                'advanced_feature' => 'required|string',
            ];
        }

        return [
            'basic_feature' => 'required|string',
        ];
    }
}
