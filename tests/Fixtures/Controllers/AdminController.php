<?php

namespace LaravelSpectrum\Tests\Fixtures\Controllers;

use Illuminate\Http\JsonResponse;

class AdminController
{
    public function users(): JsonResponse
    {
        return response()->json([]);
    }
}
