<?php

namespace Tests\Unit\Analyzers\Fixtures;

use Illuminate\Http\Resources\Json\JsonResource;

class ConditionalFieldsResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->when($request->user()->isAdmin(), $this->email),
            'secret' => $this->when($this->hasAccess(), function () {
                return $this->secret_data;
            }),
            'phone' => $this->when($request->has('include_phone'), $this->phone),
        ];
    }
}