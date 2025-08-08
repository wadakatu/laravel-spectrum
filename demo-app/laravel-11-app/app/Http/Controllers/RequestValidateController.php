<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RequestValidateController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'published' => 'boolean',
        ]);

        return response()->json(['message' => 'Post created', 'data' => $validated], 201);
    }

    public function storeWithRequestVariable(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'author' => 'required|string|max:100',
        ]);

        return response()->json(['message' => 'Article created', 'data' => $validated], 201);
    }

    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|max:10240',
            'description' => 'nullable|string|max:500',
        ]);

        return response()->json(['message' => 'File uploaded successfully'], 200);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'bio' => 'sometimes|string|max:1000',
            'avatar' => 'sometimes|url',
        ]);

        return response()->json(['message' => 'Profile updated', 'id' => $id, 'data' => $validated]);
    }

    public function testDifferentVariableNames(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'theme' => 'required|in:light,dark,auto',
            'notifications' => 'boolean',
            'language' => 'required|in:en,ja,es,fr',
        ]);

        return response()->json(['message' => 'Settings saved', 'data' => $validated]);
    }
}
