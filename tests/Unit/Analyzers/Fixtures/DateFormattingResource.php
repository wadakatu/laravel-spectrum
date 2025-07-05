<?php

namespace Tests\Unit\Analyzers\Fixtures;

use Illuminate\Http\Resources\Json\JsonResource;

class DateFormattingResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->toDateTimeString(),
            'published_date' => $this->published_at->toDateString(),
        ];
    }
}
