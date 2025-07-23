<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Search products with various query parameters
     */
    public function search(Request $request): JsonResponse
    {
        $search = $request->input('q');
        $categoryId = $request->integer('category_id');
        $minPrice = $request->float('min_price', 0.0);
        $maxPrice = $request->float('max_price');
        $inStock = $request->boolean('in_stock', true);
        $sort = $request->input('sort', 'relevance');
        $page = $request->integer('page', 1);
        $perPage = $request->integer('per_page', 20);

        // Validate sort parameter
        if (! in_array($sort, ['relevance', 'price', 'name', 'date'])) {
            $sort = 'relevance';
        }

        // Mock response
        return response()->json([
            'data' => [
                [
                    'id' => 1,
                    'name' => 'Product 1',
                    'price' => 99.99,
                    'category_id' => $categoryId,
                    'in_stock' => true,
                ],
                [
                    'id' => 2,
                    'name' => 'Product 2',
                    'price' => 149.99,
                    'category_id' => $categoryId,
                    'in_stock' => false,
                ],
            ],
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => 100,
                'search' => $search,
                'filters' => [
                    'category_id' => $categoryId,
                    'min_price' => $minPrice,
                    'max_price' => $maxPrice,
                    'in_stock' => $inStock,
                ],
                'sort' => $sort,
            ],
        ]);
    }

    /**
     * Filter products with array parameters
     */
    public function filter(Request $request): JsonResponse
    {
        $tags = $request->input('tags', []);
        $brands = $request->array('brands');
        $colors = $request->input('colors');
        $priceRange = $request->input('price_range');

        // Price range handling
        if ($priceRange && in_array($priceRange, ['0-50', '50-100', '100-500', '500+'])) {
            [$min, $max] = match ($priceRange) {
                '0-50' => [0, 50],
                '50-100' => [50, 100],
                '100-500' => [100, 500],
                '500+' => [500, null],
            };
        } else {
            $min = $max = null;
        }

        return response()->json([
            'data' => [],
            'filters' => [
                'tags' => $tags,
                'brands' => $brands,
                'colors' => $colors,
                'price_range' => $priceRange,
                'price_min' => $min,
                'price_max' => $max,
            ],
        ]);
    }
}
