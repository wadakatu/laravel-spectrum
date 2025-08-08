<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConditionalUserController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'type' => 'required|in:admin,user,guest',
        ];

        // Conditional validation based on type
        if ($request->input('type') === 'admin') {
            $rules['admin_code'] = 'required|string|size:10';
            $rules['department'] = 'required|string|max:100';
        }

        $validated = $request->validate($rules);

        return response()->json([
            'message' => 'User created',
            'data' => $validated,
        ], 201);
    }

    public function update(Request $request, string $user): JsonResponse
    {
        $rules = [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,'.$user,
        ];

        // Conditional validation
        if ($request->has('change_password')) {
            $rules['current_password'] = 'required|string';
            $rules['new_password'] = 'required|string|min:8|confirmed';
        }

        $validated = $request->validate($rules);

        return response()->json([
            'message' => 'User updated',
            'id' => $user,
            'data' => $validated,
        ]);
    }
}
