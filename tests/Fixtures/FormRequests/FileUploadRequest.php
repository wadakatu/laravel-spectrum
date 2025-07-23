<?php

namespace LaravelSpectrum\Tests\Fixtures\FormRequests;

use Illuminate\Foundation\Http\FormRequest;

class FileUploadRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'avatar' => 'required|image|max:2048',
            'resume' => 'required|file|mimes:pdf,doc,docx|max:10240',
            'portfolio' => 'nullable|url',
        ];
    }
    
    public function attributes()
    {
        return [
            'name' => 'Full Name',
            'email' => 'Email Address', 
            'avatar' => 'Profile Picture',
            'resume' => 'CV/Resume Document',
            'portfolio' => 'Portfolio Website',
        ];
    }
}