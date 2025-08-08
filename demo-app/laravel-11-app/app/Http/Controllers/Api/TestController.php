<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function index()
    {
        return response()->json([
            'message' => 'Laravel 11 Test API',
            'version' => app()->version(),
            'timestamp' => now()->toIso8601String()
        ]);
    }
    
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8'
        ]);
        
        return response()->json([
            'message' => 'User created successfully',
            'data' => $validated
        ], 201);
    }
}