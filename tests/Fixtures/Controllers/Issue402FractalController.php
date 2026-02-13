<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Fixtures\Controllers;

use Illuminate\Http\JsonResponse;
use LaravelSpectrum\Tests\Fixtures\Transformers\GetterBasedUserTransformer;
use Symfony\Component\HttpFoundation\Response;

class Issue402FractalController
{
    public function me(): JsonResponse
    {
        if (request()->boolean('unauthorized')) {
            return response()->json(['code' => 'UNAUTHORIZED'], Response::HTTP_BAD_REQUEST);
        }

        $payload = fractal()->item(new \stdClass, new GetterBasedUserTransformer)->toArray();

        return response()->json(['data' => $payload['data']]);
    }
}
