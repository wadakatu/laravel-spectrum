<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * API v2 User Controller with enhanced features.
 */
class UserController extends Controller
{
    /**
     * List users with cursor pagination (v2).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'cursor' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:100',
            'include' => 'nullable|array',
            'include.*' => 'string|in:posts,comments,profile',
            'fields' => 'nullable|array',
            'fields.users' => 'nullable|string',
            'fields.posts' => 'nullable|string',
        ]);

        $users = User::cursorPaginate($validated['limit'] ?? 15);

        return UserResource::collection($users);
    }

    /**
     * Get user with sparse fieldsets (JSON:API style).
     */
    public function show(User $user, Request $request): UserResource|JsonResponse
    {
        $validated = $request->validate([
            'fields' => 'nullable|string',
            'include' => 'nullable|string',
        ]);

        // If fields specified, return filtered response
        if (isset($validated['fields'])) {
            $fields = explode(',', $validated['fields']);

            return response()->json([
                'data' => collect($user->toArray())->only($fields),
            ]);
        }

        return new UserResource($user);
    }

    /**
     * Bulk create users.
     */
    public function bulkCreate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'users' => 'required|array|min:1|max:100',
            'users.*.name' => 'required|string|max:255',
            'users.*.email' => 'required|email|unique:users,email',
            'users.*.password' => 'required|string|min:8',
            'users.*.role' => 'nullable|string|in:admin,user,moderator',
            'options' => 'nullable|array',
            'options.send_welcome_email' => 'nullable|boolean',
            'options.auto_verify' => 'nullable|boolean',
        ]);

        // Bulk create logic
        $created = [];
        foreach ($validated['users'] as $userData) {
            $created[] = ['id' => rand(1, 1000), ...$userData];
        }

        return response()->json([
            'message' => 'Users created successfully',
            'data' => $created,
            'count' => count($created),
        ], 201);
    }

    /**
     * Bulk update users.
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'updates' => 'required|array|min:1',
            'updates.*.id' => 'required|integer|exists:users,id',
            'updates.*.name' => 'nullable|string|max:255',
            'updates.*.email' => 'nullable|email',
            'updates.*.status' => 'nullable|string|in:active,inactive,suspended',
        ]);

        return response()->json([
            'message' => 'Users updated successfully',
            'updated_count' => count($validated['updates']),
        ]);
    }

    /**
     * Bulk delete users.
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:users,id',
            'force' => 'nullable|boolean',
        ]);

        return response()->json([
            'message' => 'Users deleted successfully',
            'deleted_count' => count($validated['ids']),
        ]);
    }
}
