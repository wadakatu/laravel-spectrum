<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AdvancedFormRequest;
use Illuminate\Http\JsonResponse;

/**
 * Controller using AdvancedFormRequest with complex validation patterns.
 */
class AdvancedUserController extends Controller
{
    /**
     * Create a new user with advanced validation.
     */
    public function store(AdvancedFormRequest $request): JsonResponse
    {
        // In real app, would create user
        return response()->json([
            'message' => 'User created',
            'data' => $request->validated(),
        ], 201);
    }

    /**
     * Update user with advanced validation.
     */
    public function update(AdvancedFormRequest $request, int $id): JsonResponse
    {
        return response()->json([
            'message' => 'User updated',
            'id' => $id,
            'data' => $request->validated(),
        ]);
    }
}
