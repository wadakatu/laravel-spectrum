<?php

namespace Tests\Unit\Analyzers\Fixtures;

use Illuminate\Http\Resources\Json\JsonResource;

class MethodChainResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value, // Enum
            'full_name' => $this->first_name . ' ' . $this->last_name,
            'price_formatted' => number_format($this->price / 100, 2),
            'slug' => strtolower(str_replace(' ', '-', $this->title)),
        ];
    }
}