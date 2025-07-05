<?php

namespace Tests\Unit\Analyzers\Fixtures;

use Illuminate\Http\Resources\Json\JsonResource;

class RelationshipResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'posts' => PostResource::collection($this->whenLoaded('posts')),
            'profile' => new ProfileResource($this->whenLoaded('profile')),
            'tags' => $this->whenLoaded('tags', function () {
                return $this->tags->map(function ($tag) {
                    return [
                        'id' => $tag->id,
                        'name' => $tag->name,
                    ];
                });
            }),
        ];
    }
}
