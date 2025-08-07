<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BrokenFormRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        // This will cause a parse error during analysis
        throw new \Exception('Intentional error for testing error handling');
    }
}
