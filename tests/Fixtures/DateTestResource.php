<?php

namespace LaravelPrism\Tests\Fixtures;

use Illuminate\Http\Resources\Json\JsonResource;

class DateTestResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->format('Y-m-d'),
        ];
    }
}