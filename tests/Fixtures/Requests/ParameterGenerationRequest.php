<?php

namespace LaravelSpectrum\Tests\Fixtures\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ParameterGenerationRequest extends FormRequest
{
    public function rules(): array
    {
        if ($this->isMethod('POST')) {
            return [
                'email' => 'required|email|unique:users',
                'password' => 'required|min:8',
            ];
        }

        return [
            'email' => 'sometimes|email',
        ];
    }
}
