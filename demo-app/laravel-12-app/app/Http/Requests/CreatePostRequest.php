<?php

namespace App\Http\Requests;

use App\Enums\PostCategory;
use App\Enums\PostStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'status' => ['required', 'enum:'.PostStatus::class],
            'category' => ['required', Rule::enum(PostCategory::class)],
            'tags' => 'array',
            'tags.*' => 'string|max:50',
        ];
    }
}
