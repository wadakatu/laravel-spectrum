<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SometimesValidationRequest;
use Illuminate\Http\JsonResponse;

/**
 * Controller testing sometimes and conditional validation.
 */
class SometimesController extends Controller
{
    /**
     * Process order with conditional validation.
     */
    public function process(SometimesValidationRequest $request): JsonResponse
    {
        return response()->json([
            'message' => 'Order processed',
            'data' => $request->validated(),
        ]);
    }
}
