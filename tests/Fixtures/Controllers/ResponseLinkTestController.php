<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Fixtures\Controllers;

use Illuminate\Http\JsonResponse;
use LaravelSpectrum\Attributes\OpenApiResponseLink;

class ResponseLinkTestController
{
    #[OpenApiResponseLink(
        statusCode: 201,
        name: 'GetUserById',
        operationId: 'usersShow',
        parameters: ['user' => '$response.body#/id'],
        description: 'Fetch the created user by id',
    )]
    public function store(): JsonResponse
    {
        return response()->json(['id' => 1], 201);
    }

    #[OpenApiResponseLink(
        statusCode: 200,
        name: 'GetUserComments',
        operationRef: '#/paths/~1api~1users~1{user}~1comments/get',
        parameters: ['user' => '$response.body#/id'],
    )]
    #[OpenApiResponseLink(
        statusCode: 200,
        name: 'GetUserPosts',
        operationId: 'usersPostsIndex',
        parameters: ['user' => '$response.body#/id'],
    )]
    public function show(): JsonResponse
    {
        return response()->json(['id' => 1]);
    }

    public function index(): JsonResponse
    {
        return response()->json(['data' => []]);
    }

    #[OpenApiResponseLink(
        statusCode: 200,
        name: 'BrokenLink',
    )]
    public function invalid(): JsonResponse
    {
        return response()->json(['data' => []]);
    }
}
