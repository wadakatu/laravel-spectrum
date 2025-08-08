<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Display a listing of products
     */
    public function index(Request $request): JsonResponse
    {
        $products = [
            ['id' => 1, 'name' => 'Product A', 'price' => 29.99, 'stock' => 100],
            ['id' => 2, 'name' => 'Product B', 'price' => 49.99, 'stock' => 50],
        ];

        return response()->json([
            'data' => $products,
            'total' => count($products),
        ]);
    }

    /**
     * Search products
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'required|string|min:1',
            'category' => 'string|max:50',
            'min_price' => 'numeric|min:0',
            'max_price' => 'numeric|min:0',
        ]);

        return response()->json([
            'data' => [],
            'query' => $validated['q'],
        ]);
    }

    /**
     * Filter products
     */
    public function filter(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => 'string|max:50',
            'brand' => 'string|max:50',
            'in_stock' => 'boolean',
            'sort_by' => 'in:price,name,created_at',
            'sort_order' => 'in:asc,desc',
        ]);

        return response()->json([
            'data' => [],
            'filters' => $validated,
        ]);
    }

    /**
     * Display the specified product
     */
    public function show(string $id): JsonResponse
    {
        return response()->json([
            'data' => [
                'id' => $id,
                'name' => 'Product A',
                'price' => 29.99,
                'stock' => 100,
                'description' => 'This is a great product',
            ],
        ]);
    }
}
