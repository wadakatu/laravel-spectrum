<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    /**
     * Handle user login
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:8',
        ]);

        return response()->json([
            'message' => 'Login successful',
            'token' => 'fake-jwt-token-' . uniqid(),
            'user' => [
                'id' => 1,
                'email' => $validated['email'],
            ],
        ]);
    }

    /**
     * Handle user registration
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        return response()->json([
            'message' => 'Registration successful',
            'token' => 'fake-jwt-token-' . uniqid(),
            'user' => [
                'id' => 2,
                'name' => $validated['name'],
                'email' => $validated['email'],
            ],
        ], 201);
    }

    /**
     * Handle user logout
     */
    public function logout(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Logout successful',
        ]);
    }
}