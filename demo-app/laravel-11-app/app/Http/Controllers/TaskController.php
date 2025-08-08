<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TaskController extends Controller
{
    public function index(string $status): JsonResponse
    {
        return response()->json([
            'status' => $status,
            'tasks' => [
                ['id' => 1, 'title' => 'Task 1', 'status' => $status],
                ['id' => 2, 'title' => 'Task 2', 'status' => $status],
            ],
        ]);
    }

    public function store(Request $request, string $status, string $priority): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
        ]);

        return response()->json([
            'message' => 'Task created',
            'data' => array_merge($validated, [
                'status' => $status,
                'priority' => $priority,
            ]),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'status' => 'sometimes|in:pending,in_progress,completed',
            'priority' => 'sometimes|in:low,medium,high',
        ]);

        return response()->json([
            'message' => 'Task updated',
            'id' => $id,
            'data' => $validated,
        ]);
    }
}