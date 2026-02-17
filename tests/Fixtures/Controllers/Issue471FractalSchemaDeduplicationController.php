<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Fixtures\Controllers;

use Illuminate\Http\JsonResponse;
use LaravelSpectrum\Tests\Fixtures\Transformers\Issue467ProjectTransformer;
use Symfony\Component\HttpFoundation\Response;

class Issue471FractalSchemaDeduplicationController
{
    public function index(): JsonResponse
    {
        $projects = [
            ['project_users' => []],
        ];

        $payload = fractal()->collection($projects, new Issue467ProjectTransformer)->toArray();

        return response()->json($payload, Response::HTTP_OK);
    }

    public function show(): JsonResponse
    {
        $project = ['project_users' => []];
        $payload = fractal()->item($project, new Issue467ProjectTransformer)->toArray();

        return response()->json($payload, Response::HTTP_OK);
    }
}
