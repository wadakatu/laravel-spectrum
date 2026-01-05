<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Fixtures\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NullableFieldsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'nullable|string',
            'description' => 'nullable|string|max:500',
        ];
    }
}
