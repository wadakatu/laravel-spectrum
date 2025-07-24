<?php

namespace LaravelSpectrum\Tests\Fixtures\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProbabilityCalculationRequest extends FormRequest
{
    public function rules(): array
    {
        if ($this->isMethod('POST')) {
            if ($this->user() && $this->user()->isAdmin()) {
                return ['role' => 'required|in:admin,moderator'];
            }

            return ['role' => 'required|in:user'];
        }

        return [];
    }
}
