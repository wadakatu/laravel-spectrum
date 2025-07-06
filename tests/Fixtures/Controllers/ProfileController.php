<?php

namespace LaravelPrism\Tests\Fixtures\Controllers;

use Illuminate\Http\JsonResponse;

class ProfileController
{
    public function show(): JsonResponse
    {
        return response()->json(['name' => 'Test User']);
    }

    public function update(): JsonResponse
    {
        return response()->json(['message' => 'Profile updated']);
    }
}
