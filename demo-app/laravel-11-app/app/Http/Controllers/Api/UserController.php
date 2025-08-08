<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Display a listing of users
     */
    public function index(Request $request): JsonResponse
    {
        $users = [
            ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
            ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com'],
        ];

        return response()->json([
            'data' => $users,
            'total' => count($users),
        ]);
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        return response()->json([
            'message' => 'User created successfully',
            'data' => ['id' => 3, 'name' => $validated['name'], 'email' => $validated['email']],
        ], 201);
    }

    /**
     * Display the specified user
     */
    public function show(string $id): JsonResponse
    {
        return response()->json([
            'data' => [
                'id' => $id,
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ],
        ]);
    }

    /**
     * Update the specified user
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,'.$id,
        ]);

        return response()->json([
            'message' => 'User updated successfully',
            'data' => array_merge(['id' => $id], $validated),
        ]);
    }

    /**
     * Remove the specified user
     */
    public function destroy(string $id): JsonResponse
    {
        return response()->json([
            'message' => 'User deleted successfully',
        ], 204);
    }

    /**
     * Search users
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'required|string|min:1',
            'limit' => 'integer|min:1|max:100',
        ]);

        return response()->json([
            'data' => [],
            'query' => $validated['q'],
        ]);
    }

    /**
     * Get user profile
     */
    public function profile(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'id' => 1,
                'name' => 'Current User',
                'email' => 'current@example.com',
            ],
        ]);
    }

    /**
     * Get detailed user information
     */
    public function detailed(string $id): JsonResponse
    {
        return response()->json([
            'data' => [
                'id' => $id,
                'name' => 'User Name',
                'email' => 'user@example.com',
                'profile' => [
                    'bio' => 'User biography',
                    'avatar' => 'https://example.com/avatar.jpg',
                    'joined_at' => '2025-01-01',
                ],
                'stats' => [
                    'posts' => 42,
                    'followers' => 100,
                    'following' => 50,
                ],
            ],
        ]);
    }
}
