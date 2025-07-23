<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TestMatchController extends Controller
{
    /**
     * Test match expression with query parameters
     */
    public function matchTest(Request $request): JsonResponse
    {
        $type = $request->input('type', 'basic');
        $level = $request->integer('level', 1);

        // Test match expression for enum detection
        $result = match ($type) {
            'basic' => 'Basic Plan',
            'pro' => 'Professional Plan',
            'enterprise' => 'Enterprise Plan',
            default => 'Unknown Plan'
        };

        // Test nested match
        $discount = match (true) {
            $level >= 10 => 0.20,
            $level >= 5 => 0.10,
            $level >= 3 => 0.05,
            default => 0
        };

        return response()->json([
            'plan' => $result,
            'level' => $level,
            'discount' => $discount,
        ]);
    }
}
