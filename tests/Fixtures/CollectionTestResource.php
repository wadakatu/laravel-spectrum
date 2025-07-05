<?php

namespace LaravelPrism\Tests\Fixtures;

use Illuminate\Http\Resources\Json\JsonResource;

class CollectionTestResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'tags' => $this->tags->pluck('name'),
            'categories' => CategoryResource::collection($this->categories),
        ];
    }
}