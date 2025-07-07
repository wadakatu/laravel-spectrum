<?php

namespace LaravelSpectrum\Tests\Fixtures;

use Illuminate\Http\Resources\Json\JsonResource;

class BooleanTestResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'is_active' => $this->is_active,
            'verified' => (bool) $this->email_verified_at,
            'has_subscription' => $this->hasSubscription(),
        ];
    }
}
