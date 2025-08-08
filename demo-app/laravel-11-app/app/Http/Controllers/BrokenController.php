<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrokenController extends Controller
{
    public function brokenEndpoint(Request $request): JsonResponse
    {
        // Simulate an error
        if (rand(0, 1) === 0) {
            abort(500, 'Internal Server Error');
        }

        return response()->json(['message' => 'Success'], 200);
    }

    public function brokenResource(): JsonResponse
    {
        // Simulate a broken resource
        return response()->json(['error' => 'Resource not found'], 404);
    }
}
