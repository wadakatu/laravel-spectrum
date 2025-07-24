<?php

namespace LaravelSpectrum\Tests\Fixtures\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NestedConditionalRequest extends FormRequest
{
    public function rules(): array
    {
        if ($this->isMethod('POST')) {
            if ($this->user() && $this->user()->isAdmin()) {
                return [
                    'title' => 'required|string',
                    'published_at' => 'required|date',
                ];
            }

            return [
                'title' => 'required|string',
            ];
        }

        return [
            'title' => 'sometimes|string',
        ];
    }
}
