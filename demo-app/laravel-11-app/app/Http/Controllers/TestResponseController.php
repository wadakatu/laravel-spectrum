<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TestResponseController extends Controller
{
    public function responseJson(): JsonResponse
    {
        return response()->json([
            'message' => 'This is a JSON response',
            'timestamp' => now()->toISOString(),
        ]);
    }

    public function arrayReturn(): array
    {
        return [
            'data' => [
                'id' => 1,
                'name' => 'Test Item',
            ],
            'meta' => [
                'version' => '1.0',
            ],
        ];
    }

    public function modelReturn(string $id): array
    {
        // Simulate model return
        return [
            'id' => $id,
            'name' => 'Model Name',
            'created_at' => '2025-01-01T00:00:00Z',
            'updated_at' => '2025-01-01T00:00:00Z',
        ];
    }

    public function collectionMap(): array
    {
        $items = [
            ['id' => 1, 'name' => 'Item 1'],
            ['id' => 2, 'name' => 'Item 2'],
            ['id' => 3, 'name' => 'Item 3'],
        ];

        return array_map(function ($item) {
            return [
                'id' => $item['id'],
                'title' => strtoupper($item['name']),
            ];
        }, $items);
    }
}