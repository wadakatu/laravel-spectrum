<?php

namespace LaravelSpectrum\Tests\Fixtures\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ElseIfConditionRequest extends FormRequest
{
    public function rules(): array
    {
        if ($this->isMethod('POST')) {
            return ['status' => 'required|in:draft,published'];
        } elseif ($this->isMethod('PUT')) {
            return ['status' => 'required|in:draft,published,archived'];
        } else {
            return ['status' => 'sometimes|string'];
        }
    }
}
