<?php

namespace LaravelSpectrum\Tests\Fixtures\Controllers;

use Illuminate\Http\JsonResponse;

class OAuthController
{
    public function test(): JsonResponse
    {
        return response()->json([]);
    }
}
