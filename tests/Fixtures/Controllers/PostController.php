<?php

namespace LaravelSpectrum\Tests\Fixtures\Controllers;

use Illuminate\Http\JsonResponse;

class PostController
{
    public function index(): JsonResponse
    {
        return response()->json([]);
    }

    public function show($post): JsonResponse
    {
        return response()->json([]);
    }

    public function comments($post): JsonResponse
    {
        return response()->json([]);
    }

    public function storeComment($post): JsonResponse
    {
        return response()->json([]);
    }
}
