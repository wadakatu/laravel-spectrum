<?php

namespace LaravelSpectrum\Tests\Fixtures\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConditionalFormRequest extends FormRequest
{
    public function rules(): array
    {
        if ($this->isMethod('POST')) {
            return [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users',
                'password' => 'required|min:8',
            ];
        } elseif ($this->isMethod('PUT')) {
            return [
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email',
            ];
        }

        return [
            'name' => 'string|max:255',
        ];
    }
}
