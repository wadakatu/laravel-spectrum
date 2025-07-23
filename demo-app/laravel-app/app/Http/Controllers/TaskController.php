<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    /**
     * List tasks by status
     */
    public function index(TaskStatus $status): JsonResponse
    {
        return response()->json([
            'status' => $status->value,
            'tasks' => [
                ['id' => 1, 'title' => 'Task 1', 'status' => $status->value],
                ['id' => 2, 'title' => 'Task 2', 'status' => $status->value],
            ],
        ]);
    }

    /**
     * Create a new task with enum parameters
     */
    public function store(TaskStatus $status, TaskPriority $priority, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        return response()->json([
            'id' => rand(1, 1000),
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => $status->value,
            'priority' => $priority->value,
        ], 201);
    }

    /**
     * Update task with nullable enum parameter
     */
    public function update(int $id, ?TaskStatus $status = null, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
        ]);

        return response()->json([
            'id' => $id,
            'status' => $status?->value ?? 'unchanged',
            'updated' => $validated,
        ]);
    }
}