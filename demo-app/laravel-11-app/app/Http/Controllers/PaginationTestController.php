<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaginationTestController extends Controller
{
    /**
     * Display paginated list
     */
    public function index(Request $request): JsonResponse
    {
        $data = [
            ['id' => 1, 'name' => 'Item 1'],
            ['id' => 2, 'name' => 'Item 2'],
        ];

        return response()->json([
            'data' => $data,
            'current_page' => 1,
            'per_page' => 15,
            'total' => 100,
        ]);
    }

    /**
     * Display paginated list with resource
     */
    public function withResource(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [],
            'links' => [
                'first' => 'http://example.com/api/pagination-test/with-resource?page=1',
                'last' => 'http://example.com/api/pagination-test/with-resource?page=10',
                'prev' => null,
                'next' => 'http://example.com/api/pagination-test/with-resource?page=2',
            ],
            'meta' => [
                'current_page' => 1,
                'from' => 1,
                'last_page' => 10,
                'per_page' => 15,
                'to' => 15,
                'total' => 150,
            ],
        ]);
    }

    /**
     * Simple pagination
     */
    public function simplePagination(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [],
            'current_page' => 1,
            'per_page' => 15,
            'from' => 1,
            'to' => 15,
        ]);
    }

    /**
     * Cursor pagination
     */
    public function cursorPagination(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [],
            'next_cursor' => 'eyJpZCI6MTUsIl9wb2ludHNUb05leHRJdGVtcyI6dHJ1ZX0',
            'prev_cursor' => null,
        ]);
    }

    /**
     * Query builder pagination
     */
    public function withQueryBuilder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'sort' => 'string|in:id,name,created_at',
            'order' => 'string|in:asc,desc',
        ]);

        return response()->json([
            'data' => [],
            'pagination' => [
                'total' => 100,
                'per_page' => $validated['per_page'] ?? 15,
                'current_page' => $validated['page'] ?? 1,
                'last_page' => 7,
            ],
        ]);
    }
}
