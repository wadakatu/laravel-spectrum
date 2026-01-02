<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Fixtures\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Controller with deprecated methods for testing @deprecated annotation detection.
 */
class DeprecatedController
{
    /**
     * A deprecated endpoint.
     *
     * @deprecated Use /api/v2/users instead
     */
    public function deprecatedMethod(): JsonResponse
    {
        return response()->json(['data' => []]);
    }

    /**
     * A deprecated endpoint with additional documentation.
     *
     * This method retrieves user data but is deprecated.
     *
     * @deprecated Since version 2.0. Will be removed in version 3.0.
     */
    public function deprecatedWithReason(): JsonResponse
    {
        return response()->json(['users' => []]);
    }

    /**
     * A non-deprecated endpoint.
     */
    public function activeMethod(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    /**
     * An endpoint without any documentation.
     */
    public function undocumentedMethod(): JsonResponse
    {
        return response()->json([]);
    }
}
