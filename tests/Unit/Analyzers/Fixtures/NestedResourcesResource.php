<?php

namespace Tests\Unit\Analyzers\Fixtures;

use Illuminate\Http\Resources\Json\JsonResource;

class NestedResourcesResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'posts' => PostResource::collection($this->posts),
            'profile' => new ProfileResource($this->profile),
            'latest_post' => $this->when($this->posts->isNotEmpty(), function () {
                return new PostResource($this->posts->first());
            }),
        ];
    }
}