<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Fixtures\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller that has both regular methods AND __invoke method.
 * Used to test that calling explicit methods doesn't incorrectly use __invoke.
 */
class HybridController
{
    /**
     * Regular method - should be detected as 'list' not '__invoke'.
     */
    public function list(): JsonResponse
    {
        return response()->json(['items' => []]);
    }

    /**
     * Invokable method - only used when controller is registered as invokable.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'data' => 'required|array',
        ]);

        return response()->json(['processed' => $validated]);
    }
}
