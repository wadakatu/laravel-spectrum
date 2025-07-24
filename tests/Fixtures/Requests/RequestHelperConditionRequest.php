<?php

namespace LaravelSpectrum\Tests\Fixtures\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RequestHelperConditionRequest extends FormRequest
{
    public function rules(): array
    {
        if (request()->isMethod('POST')) {
            return [
                'name' => 'required|string',
            ];
        }

        return [
            'name' => 'sometimes|string',
        ];
    }
}
