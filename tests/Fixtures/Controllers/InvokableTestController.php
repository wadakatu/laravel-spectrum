<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Fixtures\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Invokable controller fixture for testing issue #300.
 */
class InvokableTestController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:500',
            'priority' => 'nullable|integer|in:1,2,3,4,5',
        ]);

        return response()->json([
            'status' => 'processed',
            'data' => $validated,
        ]);
    }
}
