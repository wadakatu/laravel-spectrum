<?php

namespace LaravelSpectrum\Tests\Fixtures\Controllers;

use Illuminate\Http\Request;

// Dummy Product class for testing
class Product
{
    public static function query()
    {
        return new self();
    }
    
    public function where($column, $operator, $value = null)
    {
        return $this;
    }
    
    public function orWhere($column, $operator, $value = null)
    {
        return $this;
    }
    
    public function whereNotIn($column, $values)
    {
        return $this;
    }
    
    public function whereIn($column, $values)
    {
        return $this;
    }
    
    public function whereJsonContains($column, $value)
    {
        return $this;
    }
    
    public function orderBy($column, $direction = 'asc')
    {
        return $this;
    }
    
    public function orderByRaw($sql, $bindings = [])
    {
        return $this;
    }
    
    public function paginate($perPage = 15)
    {
        return [];
    }
    
    public function when($value, $callback)
    {
        if ($value) {
            return $callback($this, $value);
        }
        return $this;
    }
    
    public function get()
    {
        return [];
    }
}

class SearchableController
{
    public function search(Request $request)
    {
        $keyword = $request->input('q');
        $categoryId = $request->integer('category_id');
        $status = $request->get('status', 'active');
        $sort = $request->input('sort', 'relevance');
        
        if (!in_array($sort, ['relevance', 'date', 'popularity'])) {
            $sort = 'relevance';
        }
        
        $includeDrafts = $request->boolean('include_drafts');
        $minPrice = $request->float('min_price', 0.0);
        $maxPrice = $request->float('max_price');
        
        $query = Product::query();
        
        if ($request->filled('q')) {
            $query->where('title', 'like', "%{$keyword}%")
                  ->orWhere('description', 'like', "%{$keyword}%");
        }
        
        if ($request->has('category_id')) {
            $query->where('category_id', $categoryId);
        }
        
        $query->where('status', $status);
        
        if (!$includeDrafts) {
            $query->whereNotIn('status', ['draft']);
        }
        
        if ($minPrice > 0) {
            $query->where('price', '>=', $minPrice);
        }
        
        if ($maxPrice) {
            $query->where('price', '<=', $maxPrice);
        }
        
        switch ($sort) {
            case 'date':
                $query->orderBy('created_at', 'desc');
                break;
            case 'popularity':
                $query->orderBy('views', 'desc');
                break;
            default:
                $query->orderByRaw('MATCH(title, description) AGAINST(? IN BOOLEAN MODE) DESC', [$keyword]);
        }
        
        return $query->paginate($request->integer('limit', 20));
    }
    
    public function filter(Request $request)
    {
        $tags = $request->input('tags', []);
        $brands = $request->array('brands');
        $inStock = $request->boolean('in_stock', true);
        
        $priceRange = $request->input('price_range');
        
        if ($priceRange && in_array($priceRange, ['0-50', '50-100', '100-500', '500+'])) {
            [$min, $max] = match($priceRange) {
                '0-50' => [0, 50],
                '50-100' => [50, 100],
                '100-500' => [100, 500],
                '500+' => [500, null],
            };
        }
        
        return Product::query()
            ->when($tags, fn($q) => $q->whereJsonContains('tags', $tags))
            ->when($brands, fn($q) => $q->whereIn('brand_id', $brands))
            ->when($inStock, fn($q) => $q->where('stock', '>', 0))
            ->get();
    }
}