<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TestMatchController extends Controller
{
    public function matchTest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pattern' => 'required|string|max:100',
            'test_string' => 'required|string|max:500',
        ]);

        $matches = preg_match('/' . $validated['pattern'] . '/', $validated['test_string']);

        return response()->json([
            'pattern' => $validated['pattern'],
            'test_string' => $validated['test_string'],
            'matches' => $matches > 0,
        ]);
    }
}