<?php

namespace LaravelSpectrum\Tests\Fixtures\Transformers;

use League\Fractal\TransformerAbstract;

class UserTransformer extends TransformerAbstract
{
    protected $availableIncludes = [
        'posts',
        'profile',
    ];

    protected $defaultIncludes = [
        'profile',
    ];

    public function transform($user)
    {
        return [
            'id' => (int) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'created_at' => $user->created_at->toIso8601String(),
            'updated_at' => $user->updated_at->toIso8601String(),
        ];
    }

    public function includeProfile($user)
    {
        return $this->item($user->profile, new ProfileTransformer);
    }

    public function includePosts($user)
    {
        return $this->collection($user->posts, new PostTransformer);
    }
}
