<?php

namespace LaravelSpectrum\Tests\Fixtures\FormRequests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use LaravelSpectrum\Tests\Fixtures\Enums\PriorityEnum;
use LaravelSpectrum\Tests\Fixtures\Enums\StatusEnum;

class EnumTestRequest extends FormRequest
{
    public function rules()
    {
        return [
            'status' => ['required', Rule::enum(StatusEnum::class)],
            'priority' => ['required', new Enum(PriorityEnum::class)],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
