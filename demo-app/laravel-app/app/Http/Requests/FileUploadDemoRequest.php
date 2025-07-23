<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FileUploadDemoRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'avatar' => 'required|image|mimes:jpeg,png|max:2048',
            'resume' => 'required|file|mimes:pdf,doc,docx|max:10240',
            'certificates' => 'array|max:5',
            'certificates.*' => 'file|mimes:pdf|max:5120',
            'bio' => 'nullable|string|max:1000',
        ];
    }

    public function attributes()
    {
        return [
            'name' => 'Full Name',
            'email' => 'Email Address',
            'avatar' => 'Profile Picture',
            'resume' => 'Resume Document',
            'certificates' => 'Professional Certificates',
            'certificates.*' => 'Certificate',
            'bio' => 'Biography',
        ];
    }
}
