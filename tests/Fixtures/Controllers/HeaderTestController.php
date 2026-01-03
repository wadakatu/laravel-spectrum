<?php

namespace LaravelSpectrum\Tests\Fixtures\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HeaderTestController
{
    /**
     * Test endpoint using header()
     */
    public function withSingleHeader(Request $request): JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        return response()->json(['key' => $idempotencyKey]);
    }

    /**
     * Test endpoint using header() with default value
     */
    public function withHeaderDefault(Request $request): JsonResponse
    {
        $requestId = $request->header('X-Request-Id', 'generated-id');

        return response()->json(['request_id' => $requestId]);
    }

    /**
     * Test endpoint using hasHeader()
     */
    public function withHasHeader(Request $request): JsonResponse
    {
        if ($request->hasHeader('X-Custom-Auth')) {
            return response()->json(['authenticated' => true]);
        }

        return response()->json(['authenticated' => false]);
    }

    /**
     * Test endpoint using bearerToken()
     */
    public function withBearerToken(Request $request): JsonResponse
    {
        $token = $request->bearerToken();

        return response()->json(['token' => $token]);
    }

    /**
     * Test endpoint using multiple headers
     */
    public function withMultipleHeaders(Request $request): JsonResponse
    {
        $tenantId = $request->header('X-Tenant-Id');
        $correlationId = $request->header('X-Correlation-Id');
        $language = $request->header('Accept-Language', 'en');

        return response()->json([
            'tenant_id' => $tenantId,
            'correlation_id' => $correlationId,
            'language' => $language,
        ]);
    }

    /**
     * Test endpoint with header and query parameter
     */
    public function withHeaderAndQuery(Request $request): JsonResponse
    {
        $apiKey = $request->header('X-Api-Key');
        $userId = $request->input('user_id');

        return response()->json([
            'api_key' => $apiKey,
            'user_id' => $userId,
        ]);
    }
}
