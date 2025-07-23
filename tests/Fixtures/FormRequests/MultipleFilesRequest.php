<?php

namespace LaravelSpectrum\Tests\Fixtures\FormRequests;

use Illuminate\Foundation\Http\FormRequest;

class MultipleFilesRequest extends FormRequest
{
    public function rules()
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'photos' => 'required|array|max:10',
            'photos.*' => 'image|max:5120|dimensions:min_width=100,min_height=100',
            'documents' => 'array',
            'documents.*' => 'file|mimes:pdf,doc,docx|max:20480',
        ];
    }
    
    public function attributes()
    {
        return [
            'title' => 'Project Title',
            'description' => 'Project Description',
            'photos' => 'Photo Gallery',
            'photos.*' => 'Photo',
            'documents' => 'Supporting Documents',
            'documents.*' => 'Document',
        ];
    }
}