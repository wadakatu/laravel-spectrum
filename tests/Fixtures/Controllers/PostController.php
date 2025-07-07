<?php

namespace LaravelSpectrum\Tests\Fixtures\Controllers;

use Illuminate\Http\JsonResponse;

class PostController
{
    public function index(): JsonResponse
    {
        return response()->json([]);
    }
}
