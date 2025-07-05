<?php

namespace Tests\Unit\Analyzers\Fixtures;

use Illuminate\Http\Resources\Json\JsonResource;

class ResourceWithMeta extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }

    public function with($request)
    {
        return [
            'meta' => [
                'version' => '1.0',
                'author' => 'Laravel Prism',
            ],
        ];
    }
}
