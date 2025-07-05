<?php

namespace Tests\Unit\Analyzers\Fixtures;

use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'         => $this->id,
            'bio'        => $this->bio,
            'avatar_url' => $this->avatar_url,
        ];
    }
}
