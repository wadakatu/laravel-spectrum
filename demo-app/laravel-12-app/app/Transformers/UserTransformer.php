<?php

declare(strict_types=1);

namespace App\Transformers;

use App\Models\User;
use League\Fractal\TransformerAbstract;

/**
 * Transforms User model data for API responses.
 *
 * This transformer demonstrates Fractal's features including:
 * - Basic transform method
 * - Available includes (optional relations)
 * - Default includes (always loaded)
 * - Type casting in returned arrays
 */
class UserTransformer extends TransformerAbstract
{
    /**
     * Relations that can be included if requested.
     *
     * @var array<string>
     */
    protected array $availableIncludes = ['posts', 'profile'];

    /**
     * Relations that are included by default.
     *
     * @var array<string>
     */
    protected array $defaultIncludes = [];

    /**
     * Transform a User model into an array.
     *
     * @return array<string, mixed>
     */
    public function transform(User $user): array
    {
        return [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'is_active' => (bool) ($user->is_active ?? true),
            'role' => $user->role ?? 'user',
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
            'links' => [
                'self' => route('api.fractal.users.show', ['id' => $user->id]),
            ],
        ];
    }

    /**
     * Include user's posts.
     *
     * @return \League\Fractal\Resource\Collection
     */
    public function includePosts(User $user)
    {
        $posts = $user->posts ?? collect();

        return $this->collection($posts, new PostTransformer);
    }

    /**
     * Include user's profile.
     *
     * @return \League\Fractal\Resource\Item|\League\Fractal\Resource\NullResource
     */
    public function includeProfile(User $user)
    {
        $profile = $user->profile ?? null;

        if (! $profile) {
            return $this->null();
        }

        return $this->item($profile, new ProfileTransformer);
    }
}
