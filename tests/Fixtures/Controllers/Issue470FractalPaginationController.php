<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Fixtures\Controllers;

use Illuminate\Http\JsonResponse;
use LaravelSpectrum\Tests\Fixtures\Transformers\Issue467ProjectTransformer;
use Symfony\Component\HttpFoundation\Response;

class Issue470FractalPaginationController
{
    public function index(): JsonResponse
    {
        $projects = [
            'data' => [],
            'nextCursor' => null,
        ];

        $payload = fractal()
            ->collection($projects['data'], new Issue467ProjectTransformer)
            ->toArray();

        return response()->json([
            'data' => $payload['data'],
            'next_cursor' => $projects['nextCursor'],
        ], Response::HTTP_OK);
    }
}
