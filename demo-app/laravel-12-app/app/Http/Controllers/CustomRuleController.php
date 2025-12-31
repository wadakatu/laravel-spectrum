<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CustomRuleRequest;
use Illuminate\Http\JsonResponse;

/**
 * Controller testing custom validation rules.
 */
class CustomRuleController extends Controller
{
    /**
     * Register user with custom validation rules.
     */
    public function register(CustomRuleRequest $request): JsonResponse
    {
        return response()->json([
            'message' => 'User registered',
            'data' => $request->validated(),
        ], 201);
    }
}
