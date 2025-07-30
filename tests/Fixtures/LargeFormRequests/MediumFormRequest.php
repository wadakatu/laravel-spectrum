<?php

namespace LaravelSpectrum\Tests\Fixtures\LargeFormRequests;

use Illuminate\Foundation\Http\FormRequest;

class MediumFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // String fields
            'string_field_5' => 'required|string|min:3|max:255',
            'string_field_10' => 'required|string|min:3|max:255',
            'string_field_15' => 'required|string|min:3|max:255',
            'string_field_20' => 'required|string|min:3|max:255',
            'string_field_25' => 'required|string|min:3|max:255',
            'string_field_30' => 'required|string|min:3|max:255',
            'string_field_35' => 'required|string|min:3|max:255',
            'string_field_40' => 'required|string|min:3|max:255',
            'string_field_45' => 'required|string|min:3|max:255',
            'string_field_50' => 'required|string|min:3|max:255',
            // Integer fields
            'integer_field_1' => 'required|integer|min:0|max:1000000',
            'integer_field_6' => 'required|integer|min:0|max:1000000',
            'integer_field_11' => 'required|integer|min:0|max:1000000',
            'integer_field_16' => 'required|integer|min:0|max:1000000',
            'integer_field_21' => 'required|integer|min:0|max:1000000',
            'integer_field_26' => 'required|integer|min:0|max:1000000',
            'integer_field_31' => 'required|integer|min:0|max:1000000',
            'integer_field_36' => 'required|integer|min:0|max:1000000',
            'integer_field_41' => 'required|integer|min:0|max:1000000',
            'integer_field_46' => 'required|integer|min:0|max:1000000',
            // Email fields
            'email_field_2' => 'required|email',
            'email_field_7' => 'required|email',
            'email_field_12' => 'required|email',
            'email_field_17' => 'required|email',
            'email_field_22' => 'required|email',
            'email_field_27' => 'required|email',
            'email_field_32' => 'required|email',
            'email_field_37' => 'required|email',
            'email_field_42' => 'required|email',
            'email_field_47' => 'required|email',
            // Date fields
            'date_field_3' => 'required|date|after:today',
            'date_field_8' => 'required|date|after:today',
            'date_field_13' => 'required|date|after:today',
            'date_field_18' => 'required|date|after:today',
            'date_field_23' => 'required|date|after:today',
            'date_field_28' => 'required|date|after:today',
            'date_field_33' => 'required|date|after:today',
            'date_field_38' => 'required|date|after:today',
            'date_field_43' => 'required|date|after:today',
            'date_field_48' => 'required|date|after:today',
            // Boolean fields
            'boolean_field_4' => 'required|boolean',
            'boolean_field_9' => 'required|boolean',
            'boolean_field_14' => 'required|boolean',
            'boolean_field_19' => 'required|boolean',
            'boolean_field_24' => 'required|boolean',
            'boolean_field_29' => 'required|boolean',
            'boolean_field_34' => 'required|boolean',
            'boolean_field_39' => 'required|boolean',
            'boolean_field_44' => 'required|boolean',
            'boolean_field_49' => 'required|boolean',
        ];
    }

    public function attributes(): array
    {
        return [
            // String fields
            'string_field_5' => 'String Field 5 Description',
            'string_field_10' => 'String Field 10 Description',
            'string_field_15' => 'String Field 15 Description',
            'string_field_20' => 'String Field 20 Description',
            'string_field_25' => 'String Field 25 Description',
            'string_field_30' => 'String Field 30 Description',
            'string_field_35' => 'String Field 35 Description',
            'string_field_40' => 'String Field 40 Description',
            'string_field_45' => 'String Field 45 Description',
            'string_field_50' => 'String Field 50 Description',
            // Integer fields
            'integer_field_1' => 'Integer Field 1 Description',
            'integer_field_6' => 'Integer Field 6 Description',
            'integer_field_11' => 'Integer Field 11 Description',
            'integer_field_16' => 'Integer Field 16 Description',
            'integer_field_21' => 'Integer Field 21 Description',
            'integer_field_26' => 'Integer Field 26 Description',
            'integer_field_31' => 'Integer Field 31 Description',
            'integer_field_36' => 'Integer Field 36 Description',
            'integer_field_41' => 'Integer Field 41 Description',
            'integer_field_46' => 'Integer Field 46 Description',
            // Email fields
            'email_field_2' => 'Email Field 2 Description',
            'email_field_7' => 'Email Field 7 Description',
            'email_field_12' => 'Email Field 12 Description',
            'email_field_17' => 'Email Field 17 Description',
            'email_field_22' => 'Email Field 22 Description',
            'email_field_27' => 'Email Field 27 Description',
            'email_field_32' => 'Email Field 32 Description',
            'email_field_37' => 'Email Field 37 Description',
            'email_field_42' => 'Email Field 42 Description',
            'email_field_47' => 'Email Field 47 Description',
            // Date fields
            'date_field_3' => 'Date Field 3 Description',
            'date_field_8' => 'Date Field 8 Description',
            'date_field_13' => 'Date Field 13 Description',
            'date_field_18' => 'Date Field 18 Description',
            'date_field_23' => 'Date Field 23 Description',
            'date_field_28' => 'Date Field 28 Description',
            'date_field_33' => 'Date Field 33 Description',
            'date_field_38' => 'Date Field 38 Description',
            'date_field_43' => 'Date Field 43 Description',
            'date_field_48' => 'Date Field 48 Description',
            // Boolean fields
            'boolean_field_4' => 'Boolean Field 4 Description',
            'boolean_field_9' => 'Boolean Field 9 Description',
            'boolean_field_14' => 'Boolean Field 14 Description',
            'boolean_field_19' => 'Boolean Field 19 Description',
            'boolean_field_24' => 'Boolean Field 24 Description',
            'boolean_field_29' => 'Boolean Field 29 Description',
            'boolean_field_34' => 'Boolean Field 34 Description',
            'boolean_field_39' => 'Boolean Field 39 Description',
            'boolean_field_44' => 'Boolean Field 44 Description',
            'boolean_field_49' => 'Boolean Field 49 Description',
        ];
    }
}
