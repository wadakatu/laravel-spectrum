<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ModernValidationRequest;
use Illuminate\Http\JsonResponse;

/**
 * Controller testing Laravel's modern validation patterns.
 */
class ModernValidationController extends Controller
{
    /**
     * Create content with modern validation rules.
     */
    public function store(ModernValidationRequest $request): JsonResponse
    {
        return response()->json([
            'message' => 'Content created',
            'data' => $request->validated(),
        ], 201);
    }
}
