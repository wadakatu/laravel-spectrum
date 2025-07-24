<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;

class ProductController extends Controller
{
    public function index()
    {
        $products = collect([
            ['id' => 1, 'name' => 'Product 1', 'price' => 99.99, 'currency' => 'USD', 'status' => 'in_stock', 'tags' => ['electronics', 'gadget'], 'created_at' => now()],
            ['id' => 2, 'name' => 'Product 2', 'price' => 199.99, 'currency' => 'EUR', 'status' => 'out_of_stock', 'tags' => ['home', 'appliance'], 'created_at' => now()],
        ]);

        return ProductResource::collection($products);
    }

    public function show($id)
    {
        $product = [
            'id' => $id,
            'name' => 'Sample Product',
            'price' => 149.99,
            'currency' => 'USD',
            'status' => 'in_stock',
            'tags' => ['example', 'test'],
            'created_at' => now(),
        ];

        return new ProductResource((object) $product);
    }
}
