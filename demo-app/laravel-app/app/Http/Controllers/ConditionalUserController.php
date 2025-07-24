<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConditionalUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class ConditionalUserController extends Controller
{
    /**
     * Store a newly created user in storage.
     */
    public function store(ConditionalUserRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        return response()->json([
            'message' => 'User created successfully',
            'data' => new UserResource($user),
        ], 201);
    }

    /**
     * Update the specified user in storage.
     */
    public function update(ConditionalUserRequest $request, User $user): JsonResponse
    {
        $user->update($request->validated());

        return response()->json([
            'message' => 'User updated successfully',
            'data' => new UserResource($user),
        ]);
    }
}
