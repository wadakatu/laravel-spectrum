<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Http\Resources\PostResource;
use App\Models\User;
use App\Models\Post;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Controller testing patterns found in popular OSS Laravel projects.
 */
class OssPatternController extends Controller
{
    /**
     * Constructor dependency injection pattern (common in OSS).
     */
    public function __construct(
        private readonly UserService $userService,
    ) {}

    // ========================================
    // 1. JSON API Style Filtering (Spatie Query Builder pattern)
    // ========================================

    /**
     * Filter resources using JSON API style query parameters.
     * Pattern: ?filter[name]=value&filter[status]=active
     */
    public function jsonApiFilter(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'filter' => 'nullable|array',
            'filter.name' => 'nullable|string|max:255',
            'filter.email' => 'nullable|email',
            'filter.status' => 'nullable|string|in:active,inactive,pending',
            'filter.created_after' => 'nullable|date',
            'filter.created_before' => 'nullable|date',
            'sort' => 'nullable|string',
            'include' => 'nullable|string',
            'page' => 'nullable|array',
            'page.number' => 'nullable|integer|min:1',
            'page.size' => 'nullable|integer|min:1|max:100',
        ]);

        return response()->json(['data' => [], 'meta' => ['filters' => $validated]]);
    }

    /**
     * Sparse fieldsets pattern (JSON API).
     * Pattern: ?fields[users]=name,email&fields[posts]=title
     */
    public function sparseFieldsets(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fields' => 'nullable|array',
            'fields.users' => 'nullable|string',
            'fields.posts' => 'nullable|string',
            'fields.comments' => 'nullable|string',
        ]);

        return response()->json(['data' => [], 'meta' => ['fields' => $validated]]);
    }

    // ========================================
    // 2. Service Class Delegation Pattern
    // ========================================

    /**
     * Delegate validation to service class.
     * Common in larger OSS projects.
     */
    public function createViaService(Request $request): JsonResponse
    {
        // Service handles the validation internally
        $user = $this->userService->createUser($request->all());

        return response()->json(['data' => $user], 201);
    }

    /**
     * Service with explicit validation in controller.
     */
    public function updateViaService(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'settings' => 'nullable|array',
        ]);

        $user = $this->userService->updateUser($id, $validated);

        return response()->json(['data' => $user]);
    }

    // ========================================
    // 3. Cursor Pagination Pattern (JSON API)
    // ========================================

    /**
     * Cursor-based pagination (common in modern APIs).
     */
    public function cursorPaginated(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'cursor' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $users = User::cursorPaginate($validated['per_page'] ?? 15);

        return UserResource::collection($users);
    }

    // ========================================
    // 4. Polymorphic Relationship Patterns
    // ========================================

    /**
     * Return polymorphic commentable items.
     */
    public function polymorphicComments(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'commentable_type' => 'required|string|in:post,video,photo',
            'commentable_id' => 'required|integer',
            'body' => 'required|string|max:1000',
        ]);

        return response()->json([
            'data' => [
                'id' => 1,
                'body' => $validated['body'],
                'commentable_type' => $validated['commentable_type'],
                'commentable_id' => $validated['commentable_id'],
            ],
        ], 201);
    }

    // ========================================
    // 5. Batch Operations (Common in APIs)
    // ========================================

    /**
     * Batch delete resources.
     */
    public function batchDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:100',
            'ids.*' => 'integer|distinct',
        ]);

        return response()->json([
            'deleted' => count($validated['ids']),
            'ids' => $validated['ids'],
        ]);
    }

    /**
     * Batch update resources.
     */
    public function batchUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'resources' => 'required|array|min:1|max:50',
            'resources.*.id' => 'required|integer',
            'resources.*.attributes' => 'required|array',
            'resources.*.attributes.name' => 'nullable|string|max:255',
            'resources.*.attributes.status' => 'nullable|string|in:active,inactive',
        ]);

        return response()->json([
            'updated' => count($validated['resources']),
        ]);
    }

    // ========================================
    // 6. Multi-tenancy Patterns
    // ========================================

    /**
     * Tenant-scoped resource access.
     */
    public function tenantResource(Request $request, string $tenant): JsonResponse
    {
        $validated = $request->validate([
            'resource_type' => 'required|string|in:users,posts,settings',
        ]);

        return response()->json([
            'tenant' => $tenant,
            'resource_type' => $validated['resource_type'],
            'data' => [],
        ]);
    }

    // ========================================
    // 7. Complex Search Patterns
    // ========================================

    /**
     * Full-text search with advanced options.
     */
    public function advancedSearch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'required|string|min:2|max:100',
            'fields' => 'nullable|array',
            'fields.*' => 'string|in:title,body,tags,author',
            'operator' => 'nullable|string|in:and,or',
            'fuzziness' => 'nullable|integer|min:0|max:2',
            'highlight' => 'nullable|boolean',
            'aggregations' => 'nullable|array',
            'aggregations.*' => 'string|in:category,date,author',
        ]);

        return response()->json([
            'query' => $validated,
            'results' => [],
            'total' => 0,
        ]);
    }

    // ========================================
    // 8. Webhook Receiver Patterns
    // ========================================

    /**
     * Receive webhook from external service.
     */
    public function receiveWebhook(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event' => 'required|string',
            'payload' => 'required|array',
            'signature' => 'required|string',
            'timestamp' => 'required|integer',
        ]);

        return response()->json(['received' => true]);
    }

    // ========================================
    // 9. Rate Limit Info Response
    // ========================================

    /**
     * Return rate limit headers (common API pattern).
     */
    public function withRateLimitInfo(): JsonResponse
    {
        return response()->json(['data' => 'test'])
            ->header('X-RateLimit-Limit', '60')
            ->header('X-RateLimit-Remaining', '59')
            ->header('X-RateLimit-Reset', (string)(time() + 3600));
    }

    // ========================================
    // 10. GraphQL-like Selection Pattern
    // ========================================

    /**
     * Select specific fields (GraphQL-like).
     */
    public function selectFields(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'select' => 'nullable|array',
            'select.*' => 'string|in:id,name,email,created_at,updated_at,profile,posts',
            'expand' => 'nullable|array',
            'expand.*' => 'string|in:profile,posts,comments',
            'depth' => 'nullable|integer|min:1|max:3',
        ]);

        return response()->json(['data' => [], 'meta' => $validated]);
    }

    // ========================================
    // 11. Idempotency Key Pattern
    // ========================================

    /**
     * Handle idempotent requests (Stripe pattern).
     */
    public function idempotentCreate(Request $request): JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        $validated = $request->validate([
            'amount' => 'required|integer|min:1',
            'currency' => 'required|string|size:3',
            'description' => 'nullable|string|max:500',
        ]);

        return response()->json([
            'id' => 'txn_' . uniqid(),
            'idempotency_key' => $idempotencyKey,
            'data' => $validated,
        ], 201);
    }

    // ========================================
    // 12. Soft Delete with Restore Pattern
    // ========================================

    /**
     * List with trashed option.
     */
    public function listWithTrashed(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'with_trashed' => 'nullable|boolean',
            'only_trashed' => 'nullable|boolean',
        ]);

        return response()->json(['data' => [], 'filters' => $validated]);
    }

    /**
     * Restore soft-deleted resource.
     */
    public function restore(int $id): JsonResponse
    {
        return response()->json(['restored' => true, 'id' => $id]);
    }

    // ========================================
    // 13. Timezone-aware Patterns
    // ========================================

    /**
     * Handle timezone-aware date queries.
     */
    public function timezoneQuery(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start' => 'required|date',
            'end' => 'required|date|after:start',
            'timezone' => 'required|timezone:all',
            'granularity' => 'nullable|string|in:hour,day,week,month',
        ]);

        return response()->json(['data' => [], 'query' => $validated]);
    }
}
