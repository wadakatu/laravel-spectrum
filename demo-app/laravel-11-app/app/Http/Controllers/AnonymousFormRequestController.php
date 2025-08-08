<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnonymousFormRequestController extends Controller
{
    /**
     * Store a new blog post using anonymous FormRequest
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(
            (new class extends FormRequest
            {
                public function rules(): array
                {
                    return [
                        'title' => 'required|string|max:255',
                        'content' => 'required|string|min:10',
                        'author' => 'required|string|max:100',
                        'tags' => 'array',
                        'tags.*' => 'string|max:50',
                        'published' => 'boolean',
                        'publish_date' => 'nullable|date|after:today',
                    ];
                }

                public function messages(): array
                {
                    return [
                        'title.required' => 'The blog post title is required',
                        'content.min' => 'The content must be at least 10 characters',
                        'author.required' => 'Please provide the author name',
                        'publish_date.after' => 'The publish date must be in the future',
                    ];
                }

                public function attributes(): array
                {
                    return [
                        'title' => 'Blog Title',
                        'content' => 'Blog Content',
                        'author' => 'Author Name',
                        'publish_date' => 'Publication Date',
                    ];
                }
            })->rules()
        );

        // Simulate storing the blog post
        return response()->json([
            'message' => 'Blog post created successfully',
            'data' => $validated,
        ], 201);
    }

    /**
     * Update user profile with anonymous FormRequest using array rules
     */
    public function updateProfile(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(
            (new class extends FormRequest
            {
                public function rules(): array
                {
                    return [
                        'name' => ['required', 'string', 'max:255'],
                        'email' => ['required', 'email', 'unique:users,email'],
                        'bio' => ['nullable', 'string', 'max:500'],
                        'avatar' => ['nullable', 'image', 'max:2048'],
                        'preferences' => ['array'],
                        'preferences.theme' => ['in:light,dark,auto'],
                        'preferences.notifications' => ['boolean'],
                    ];
                }
            })->rules()
        );

        // Simulate updating the profile
        return response()->json([
            'message' => 'Profile updated successfully',
            'data' => array_merge(['id' => $id], $validated),
        ]);
    }

    /**
     * Create a product with complex validation
     */
    public function createProduct(Request $request): JsonResponse
    {
        // First validation - basic fields
        $request->validate([
            'sku' => 'required|string|unique:products',
            'category_id' => 'required|integer|exists:categories,id',
        ]);

        // Second validation - detailed fields using anonymous FormRequest
        $validated = $request->validate(
            (new class extends FormRequest
            {
                public function rules(): array
                {
                    return [
                        'name' => 'required|string|max:255',
                        'description' => 'required|string',
                        'price' => 'required|numeric|min:0.01',
                        'stock' => 'required|integer|min:0',
                        'weight' => 'nullable|numeric|min:0',
                        'dimensions' => 'array',
                        'dimensions.length' => 'required_with:dimensions|numeric|min:0',
                        'dimensions.width' => 'required_with:dimensions|numeric|min:0',
                        'dimensions.height' => 'required_with:dimensions|numeric|min:0',
                    ];
                }

                public function messages(): array
                {
                    return [
                        'price.min' => 'Price must be greater than 0',
                        'stock.min' => 'Stock cannot be negative',
                        'dimensions.*.required_with' => 'All dimensions must be provided together',
                    ];
                }
            })->rules()
        );

        return response()->json([
            'message' => 'Product created successfully',
            'data' => $validated,
        ], 201);
    }

    /**
     * Simple registration with minimal anonymous FormRequest
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate(
            (new class extends FormRequest
            {
                public function rules(): array
                {
                    return [
                        'username' => 'required|string|alpha_num|min:3|max:20|unique:users',
                        'password' => 'required|string|min:8|confirmed',
                        'terms_accepted' => 'required|accepted',
                    ];
                }
            })->rules()
        );

        return response()->json([
            'message' => 'Registration successful',
            'username' => $validated['username'],
        ], 201);
    }
}
