<?php

namespace LaravelPrism\Tests\Fixtures;

use Illuminate\Http\Resources\Json\JsonResource;

class NestedTestResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'profile' => [
                'bio' => $this->bio,
                'avatar' => $this->avatar_url,
            ],
            'posts_count' => $this->posts()->count(),
        ];
    }
}