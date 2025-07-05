<?php

namespace Tests\Unit\Analyzers\Fixtures;

use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'      => $this->id,
            'title'   => $this->title,
            'content' => $this->content,
        ];
    }
}
