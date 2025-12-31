<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Service class pattern used in many OSS projects.
 * Validation is handled inside the service, not in controller.
 */
class UserService
{
    /**
     * Create a user with internal validation.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function createUser(array $data): array
    {
        $validated = Validator::make($data, [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'nullable|string|in:admin,user,moderator',
            'settings' => 'nullable|array',
            'settings.notifications' => 'nullable|boolean',
            'settings.theme' => 'nullable|string|in:light,dark,auto',
        ])->validate();

        // In real app, would create user
        return [
            'id' => rand(1, 1000),
            ...$validated,
            'password' => '[hidden]',
        ];
    }

    /**
     * Update a user.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateUser(int $id, array $data): array
    {
        return [
            'id' => $id,
            ...$data,
        ];
    }
}
