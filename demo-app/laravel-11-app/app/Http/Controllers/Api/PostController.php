<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PostController extends Controller
{
    /**
     * Display a listing of posts
     */
    public function index(Request $request): JsonResponse
    {
        $posts = [
            ['id' => 1, 'title' => 'First Post', 'content' => 'This is the first post'],
            ['id' => 2, 'title' => 'Second Post', 'content' => 'This is the second post'],
        ];

        return response()->json([
            'data' => $posts,
            'total' => count($posts),
        ]);
    }

    /**
     * Store a newly created post
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'status' => 'required|in:draft,published,archived',
            'tags' => 'array',
            'tags.*' => 'string|max:50',
        ]);

        return response()->json([
            'message' => 'Post created successfully',
            'data' => array_merge(['id' => 3], $validated),
        ], 201);
    }

    /**
     * Display the specified post
     */
    public function show(string $id): JsonResponse
    {
        return response()->json([
            'data' => [
                'id' => $id,
                'title' => 'Sample Post',
                'content' => 'This is a sample post content',
                'status' => 'published',
            ],
        ]);
    }

    /**
     * Update the specified post
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'status' => 'sometimes|in:draft,published,archived',
        ]);

        return response()->json([
            'message' => 'Post updated successfully',
            'data' => array_merge(['id' => $id], $validated),
        ]);
    }

    /**
     * Remove the specified post
     */
    public function destroy(string $id): JsonResponse
    {
        return response()->json([
            'message' => 'Post deleted successfully',
        ], 204);
    }
}