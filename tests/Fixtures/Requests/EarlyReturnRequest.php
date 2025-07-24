<?php

namespace LaravelSpectrum\Tests\Fixtures\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EarlyReturnRequest extends FormRequest
{
    public function rules(): array
    {
        if ($this->isMethod('DELETE')) {
            return [];
        }

        if ($this->isMethod('POST')) {
            return [
                'name' => 'required|string',
                'email' => 'required|email',
            ];
        }

        return [
            'name' => 'sometimes|string',
            'email' => 'sometimes|email',
        ];
    }
}
