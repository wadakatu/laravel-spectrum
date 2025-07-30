<?php

namespace LaravelSpectrum\Tests\Fixtures\LargeFormRequests;

use Illuminate\Foundation\Http\FormRequest;

class ExtremelyLargeFormRequest extends FormRequest
{
    public function rules(): array
    {
        $rules = [];

        // Generate 500 fields with mixed validation rules
        for ($i = 1; $i <= 500; $i++) {
            switch ($i % 10) {
                case 0:
                    $rules["string_field_{$i}"] = 'required|string|min:3|max:255';
                    break;
                case 1:
                    $rules["integer_field_{$i}"] = 'required|integer|min:0|max:1000000';
                    break;
                case 2:
                    $rules["email_field_{$i}"] = 'required|email|unique:users,email';
                    break;
                case 3:
                    $rules["date_field_{$i}"] = 'required|date|after:today';
                    break;
                case 4:
                    $rules["boolean_field_{$i}"] = 'required|boolean';
                    break;
                case 5:
                    $rules["array_field_{$i}"] = 'required|array|min:1';
                    break;
                case 6:
                    $rules["numeric_field_{$i}"] = 'required|numeric|between:0,99.99';
                    break;
                case 7:
                    $rules["url_field_{$i}"] = 'required|url';
                    break;
                case 8:
                    $rules["json_field_{$i}"] = 'required|json';
                    break;
                case 9:
                    $rules["optional_field_{$i}"] = 'sometimes|nullable|string';
                    break;
            }
        }

        return $rules;
    }

    public function attributes(): array
    {
        $attributes = [];

        for ($i = 1; $i <= 500; $i++) {
            switch ($i % 10) {
                case 0:
                    $attributes["string_field_{$i}"] = "String Field {$i}";
                    break;
                case 1:
                    $attributes["integer_field_{$i}"] = "Integer Field {$i}";
                    break;
                case 2:
                    $attributes["email_field_{$i}"] = "Email Field {$i}";
                    break;
                case 3:
                    $attributes["date_field_{$i}"] = "Date Field {$i}";
                    break;
                case 4:
                    $attributes["boolean_field_{$i}"] = "Boolean Field {$i}";
                    break;
                case 5:
                    $attributes["array_field_{$i}"] = "Array Field {$i}";
                    break;
                case 6:
                    $attributes["numeric_field_{$i}"] = "Numeric Field {$i}";
                    break;
                case 7:
                    $attributes["url_field_{$i}"] = "URL Field {$i}";
                    break;
                case 8:
                    $attributes["json_field_{$i}"] = "JSON Field {$i}";
                    break;
                case 9:
                    $attributes["optional_field_{$i}"] = "Optional Field {$i}";
                    break;
            }
        }

        return $attributes;
    }
}