<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

/**
 * FormRequest testing Laravel's modern Rule objects.
 */
class ModernValidationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // File rule object (Laravel 9+)
            'document' => [
                'required',
                File::types(['pdf', 'docx'])
                    ->min(1)  // 1KB minimum
                    ->max(10 * 1024),  // 10MB maximum
            ],

            // Image file rule object
            'photo' => [
                'nullable',
                File::image()
                    ->min(10)  // 10KB
                    ->max(5 * 1024)  // 5MB
                    ->dimensions(
                        fn ($dimensions) => $dimensions
                            ->minWidth(200)
                            ->minHeight(200)
                            ->maxWidth(4000)
                            ->maxHeight(4000)
                    ),
            ],

            // Multiple file uploads
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => [
                File::types(['pdf', 'jpg', 'png'])
                    ->max(2 * 1024),
            ],

            // String length constraints
            'title' => ['required', 'string', 'min:5', 'max:100'],
            'content' => ['required', 'string', 'min:20', 'max:10000'],

            // Lowercase/Uppercase rules (Laravel 10+)
            'slug' => ['required', 'string', 'lowercase', 'max:50'],
            'code' => ['nullable', 'string', 'uppercase', 'size:6'],

            // ASCII only
            'sku' => ['nullable', 'ascii', 'max:20'],

            // Decimal validation
            'price' => ['required', 'decimal:2', 'min:0'],
            'weight' => ['nullable', 'decimal:0,3', 'min:0'],

            // List validation (array of specific type)
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer', 'exists:categories,id'],

            // Date before/after with relative dates
            'publish_at' => ['nullable', 'date', 'after:now'],
            'expire_at' => ['nullable', 'date', 'after:publish_at', 'before:+1 year'],

            // Filled vs present vs required
            'optional_filled' => ['nullable', 'filled'],  // if present, must not be empty
            'must_be_present' => ['present'],  // must be in request (even if empty)

            // Starts with / Ends with
            'reference' => ['nullable', 'string', 'starts_with:REF-', 'max:20'],
            'serial' => ['nullable', 'string', 'ends_with:-FINAL', 'max:30'],

            // UUID variations
            'external_id' => ['nullable', 'uuid'],
            'legacy_id' => ['nullable', 'ulid'],

            // Hex color
            'color' => ['nullable', 'hex_color'],

            // MAC address
            'device_mac' => ['nullable', 'mac_address'],
        ];
    }
}
