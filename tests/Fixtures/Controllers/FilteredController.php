<?php

namespace LaravelSpectrum\Tests\Fixtures\Controllers;

use Illuminate\Http\Request;

// Dummy User class for testing
class User
{
    public static function query()
    {
        return new self;
    }

    public function where($column, $operator, $value = null)
    {
        return $this;
    }

    public function orderBy($column, $direction = 'asc')
    {
        return $this;
    }

    public function paginate($perPage = 15)
    {
        return [];
    }
}

class FilteredController
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $category = $request->get('category');
        $status = $request->query('status', 'all');
        $perPage = $request->integer('per_page', 15);
        $sortBy = $request->input('sort_by', 'created_at');
        $order = $request->input('order', 'desc');

        $query = User::query();

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($category) {
            $query->where('category_id', $category);
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return $query->orderBy($sortBy, $order)->paginate($perPage);
    }
}
