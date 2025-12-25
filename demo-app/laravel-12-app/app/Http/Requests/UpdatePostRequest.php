<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PostCategory;
use App\Enums\PostStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'status' => ['sometimes', Rule::enum(PostStatus::class)],
            'category' => ['sometimes', Rule::enum(PostCategory::class)],
            'tags' => 'sometimes|array',
            'tags.*' => 'string|max:50',
        ];
    }
}
