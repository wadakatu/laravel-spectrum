<?php

namespace LaravelSpectrum\Tests\Fixtures\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MethodBasedConditionalRequest extends FormRequest
{
    public function rules(): array
    {
        $rules = $this->baseRules();

        if ($this->user() && $this->user()->isAdmin()) {
            return array_merge($rules, [
                'role' => 'required|in:admin,moderator,user',
                'permissions' => 'array',
                'permissions.*' => 'string|exists:permissions,name',
            ]);
        }

        return array_merge($rules, [
            'department' => 'required|string',
        ]);
    }

    private function baseRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
        ];
    }
}
