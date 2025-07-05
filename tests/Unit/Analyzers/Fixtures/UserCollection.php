<?php

namespace Tests\Unit\Analyzers\Fixtures;

use Illuminate\Http\Resources\Json\ResourceCollection;

class UserCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total' => $this->count(),
            ],
        ];
    }
}
