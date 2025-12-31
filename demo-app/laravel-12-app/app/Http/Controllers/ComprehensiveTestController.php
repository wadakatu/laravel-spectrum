<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Http\Resources\UserResource;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Comprehensive test controller for OpenAPI generation testing.
 * Tests various Laravel patterns and edge cases.
 */
class ComprehensiveTestController extends Controller
{
    // ========================================
    // 1. Nested Resources
    // ========================================

    /**
     * Get posts for a specific user (nested resource).
     */
    public function userPosts(User $user): JsonResponse
    {
        return response()->json([
            'user_id' => $user->id,
            'posts' => $user->posts ?? [],
        ]);
    }

    /**
     * Get a specific post for a user (deeply nested).
     */
    public function showUserPost(User $user, Post $post): PostResource
    {
        return new PostResource($post);
    }

    /**
     * Get comments for a user's post (triple nested).
     */
    public function userPostComments(int $userId, int $postId): JsonResponse
    {
        return response()->json([
            'user_id' => $userId,
            'post_id' => $postId,
            'comments' => [],
        ]);
    }

    // ========================================
    // 2. Route Model Binding with Custom Keys
    // ========================================

    /**
     * Find user by UUID instead of ID.
     */
    public function findByUuid(string $uuid): UserResource
    {
        $user = User::where('uuid', $uuid)->firstOrFail();

        return new UserResource($user);
    }

    /**
     * Find user by slug.
     */
    public function findBySlug(string $slug): UserResource
    {
        $user = User::where('slug', $slug)->firstOrFail();

        return new UserResource($user);
    }

    // ========================================
    // 3. Multiple Response Status Codes
    // ========================================

    /**
     * Create resource with 201 response.
     */
    public function createWithStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
        ]);

        return response()->json([
            'message' => 'Created successfully',
            'data' => $validated,
        ], 201);
    }

    /**
     * No content response (204).
     */
    public function deleteResource(int $id): Response
    {
        // Delete logic here
        return response()->noContent();
    }

    /**
     * Accepted response (202) for async processing.
     */
    public function asyncProcess(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'job_type' => 'required|string',
            'payload' => 'required|array',
        ]);

        return response()->json([
            'message' => 'Job queued for processing',
            'job_id' => 'job_'.uniqid(),
        ], 202);
    }

    // ========================================
    // 4. Complex Array/Nested Validation
    // ========================================

    /**
     * Handle nested array validation.
     */
    public function nestedArrayValidation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'users' => 'required|array|min:1',
            'users.*.name' => 'required|string|max:255',
            'users.*.email' => 'required|email',
            'users.*.roles' => 'array',
            'users.*.roles.*' => 'string|in:admin,user,moderator',
            'users.*.profile' => 'array',
            'users.*.profile.bio' => 'nullable|string|max:1000',
            'users.*.profile.avatar_url' => 'nullable|url',
            'metadata' => 'array',
            'metadata.source' => 'required_with:metadata|string',
            'metadata.tags' => 'array',
            'metadata.tags.*' => 'string|max:50',
        ]);

        return response()->json(['data' => $validated]);
    }

    /**
     * Matrix/2D array validation.
     */
    public function matrixValidation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'matrix' => 'required|array',
            'matrix.*' => 'array',
            'matrix.*.*' => 'numeric',
            'dimensions' => 'required|array',
            'dimensions.rows' => 'required|integer|min:1',
            'dimensions.cols' => 'required|integer|min:1',
        ]);

        return response()->json(['data' => $validated]);
    }

    // ========================================
    // 5. UUID and Special Parameter Types
    // ========================================

    /**
     * UUID path parameter.
     */
    public function getByUuid(string $uuid): JsonResponse
    {
        // UUID format: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
        return response()->json(['uuid' => $uuid]);
    }

    /**
     * Date range query parameters.
     */
    public function dateRangeQuery(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date|date_format:Y-m-d',
            'end_date' => 'required|date|date_format:Y-m-d|after:start_date',
            'include_time' => 'boolean',
        ]);

        return response()->json(['filters' => $validated]);
    }

    /**
     * DateTime with timezone.
     */
    public function datetimeQuery(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'timestamp' => 'required|date_format:Y-m-d\TH:i:sP',
            'timezone' => 'required|timezone',
        ]);

        return response()->json(['data' => $validated]);
    }

    // ========================================
    // 6. Optional and Nullable Parameters
    // ========================================

    /**
     * Multiple optional query parameters.
     */
    public function optionalParams(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort_by' => 'nullable|string|in:name,created_at,updated_at',
            'sort_order' => 'nullable|string|in:asc,desc',
            'filters' => 'nullable|array',
            'filters.status' => 'nullable|string',
            'filters.category' => 'nullable|integer',
        ]);

        return response()->json(['params' => $validated]);
    }

    // ========================================
    // 7. Binary/File Responses
    // ========================================

    /**
     * Download file response.
     */
    public function downloadFile(int $id): BinaryFileResponse
    {
        $path = storage_path('app/files/document.pdf');

        return response()->download($path, 'document.pdf');
    }

    /**
     * Stream large file.
     */
    public function streamFile(int $id): StreamedResponse
    {
        return response()->stream(function () {
            // Stream content
            echo 'streamed content';
        }, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="export.csv"',
        ]);
    }

    /**
     * Return image/binary content.
     */
    public function getImage(string $filename): Response
    {
        $content = file_get_contents(storage_path("app/images/{$filename}"));

        return response($content, 200, [
            'Content-Type' => 'image/png',
        ]);
    }

    // ========================================
    // 8. Different Content Types
    // ========================================

    /**
     * XML response.
     */
    public function xmlResponse(): Response
    {
        $xml = '<?xml version="1.0"?><root><item>test</item></root>';

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
        ]);
    }

    /**
     * Plain text response.
     */
    public function textResponse(): Response
    {
        return response('Plain text content', 200, [
            'Content-Type' => 'text/plain',
        ]);
    }

    // ========================================
    // 9. Conditional/Polymorphic Returns
    // ========================================

    /**
     * Return different types based on condition.
     */
    public function conditionalReturn(Request $request): UserResource|PostResource
    {
        $type = $request->query('type', 'user');

        if ($type === 'post') {
            return new PostResource(Post::first());
        }

        return new UserResource(User::first());
    }

    /**
     * Return collection or single item.
     */
    public function flexibleReturn(Request $request): JsonResponse
    {
        $ids = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        if (count($ids['ids']) === 1) {
            return response()->json(['item' => ['id' => $ids['ids'][0]]]);
        }

        return response()->json(['items' => array_map(fn ($id) => ['id' => $id], $ids['ids'])]);
    }

    // ========================================
    // 10. Headers and Custom Responses
    // ========================================

    /**
     * Response with custom headers.
     */
    public function withCustomHeaders(): JsonResponse
    {
        return response()->json(['data' => 'test'])
            ->header('X-Custom-Header', 'custom-value')
            ->header('X-Request-Id', uniqid())
            ->header('X-Rate-Limit-Remaining', '99');
    }

    /**
     * Cacheable response with ETag.
     */
    public function cacheableResponse(int $id): JsonResponse
    {
        $data = ['id' => $id, 'name' => 'Test'];
        $etag = md5(json_encode($data));

        return response()->json($data)
            ->header('ETag', $etag)
            ->header('Cache-Control', 'max-age=3600');
    }

    // ========================================
    // 11. Deprecated Endpoints
    // ========================================

    /**
     * @deprecated Use /api/v2/users instead
     */
    public function deprecatedEndpoint(): JsonResponse
    {
        return response()->json([
            'warning' => 'This endpoint is deprecated',
            'data' => [],
        ]);
    }

    // ========================================
    // 12. Complex Query String Patterns
    // ========================================

    /**
     * Array query parameters with different formats.
     */
    public function arrayQueryParams(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'array',
            'ids.*' => 'integer',
            'tags' => 'array',
            'tags.*' => 'string',
            'range' => 'array',
            'range.min' => 'numeric',
            'range.max' => 'numeric',
        ]);

        return response()->json(['params' => $validated]);
    }

    /**
     * Boolean query parameters.
     */
    public function booleanParams(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'active' => 'boolean',
            'verified' => 'boolean',
            'include_deleted' => 'boolean',
        ]);

        return response()->json(['filters' => $validated]);
    }

    // ========================================
    // 13. Numeric Constraints
    // ========================================

    /**
     * Numeric validation with constraints.
     */
    public function numericConstraints(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'price' => 'required|numeric|min:0|max:999999.99',
            'quantity' => 'required|integer|min:1|max:1000',
            'discount_percent' => 'nullable|numeric|between:0,100',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        return response()->json(['data' => $validated]);
    }

    // ========================================
    // 14. String Format Validation
    // ========================================

    /**
     * Various string format validations.
     */
    public function stringFormats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'url' => 'required|url',
            'ip_address' => 'required|ip',
            'ipv4' => 'nullable|ipv4',
            'ipv6' => 'nullable|ipv6',
            'mac_address' => 'nullable|mac_address',
            'uuid' => 'required|uuid',
            'ulid' => 'nullable|ulid',
            'json_string' => 'nullable|json',
            'regex_match' => 'nullable|regex:/^[A-Z]{2,3}-\d{4}$/',
        ]);

        return response()->json(['data' => $validated]);
    }

    // ========================================
    // 15. Accepted/Required Variations
    // ========================================

    /**
     * Conditional required fields.
     */
    public function conditionalRequired(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payment_type' => 'required|in:card,bank,crypto',
            'card_number' => 'required_if:payment_type,card|nullable|string',
            'card_expiry' => 'required_if:payment_type,card|nullable|date_format:m/y',
            'bank_account' => 'required_if:payment_type,bank|nullable|string',
            'bank_routing' => 'required_if:payment_type,bank|nullable|string',
            'wallet_address' => 'required_if:payment_type,crypto|nullable|string',
            'notes' => 'required_with:metadata|nullable|string',
            'metadata' => 'nullable|array',
        ]);

        return response()->json(['data' => $validated]);
    }

    // ========================================
    // 16. Exclude/Prohibit Rules
    // ========================================

    /**
     * Mutually exclusive fields.
     */
    public function mutuallyExclusive(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|integer|prohibits:email,username',
            'email' => 'nullable|email|prohibits:user_id',
            'username' => 'nullable|string|prohibits:user_id',
        ]);

        return response()->json(['data' => $validated]);
    }
}
