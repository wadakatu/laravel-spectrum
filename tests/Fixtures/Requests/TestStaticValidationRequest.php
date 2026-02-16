<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Fixtures\Requests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidationValidator;

class TestStaticValidationRequest
{
    public static function validation(Request $request): ValidationValidator
    {
        return Validator::make($request->all(), [
            'country_code' => 'required|string|max:100',
            'payment_method_id' => 'required|string',
            'city' => 'nullable|string|max:100',
        ]);
    }
}
