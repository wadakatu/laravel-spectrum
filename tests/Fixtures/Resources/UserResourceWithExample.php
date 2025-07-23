<?php

namespace LaravelSpectrum\Tests\Fixtures\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use LaravelSpectrum\Contracts\HasExamples;

class UserResourceWithExample extends JsonResource implements HasExamples
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }

    public function getExample(): array
    {
        return [
            'id' => 123,
            'name' => 'Test User',
            'email' => 'test@example.com',
        ];
    }

    public function getExamples(): array
    {
        return [
            'admin' => [
                'id' => 1,
                'name' => 'Admin User',
                'email' => 'admin@example.com',
            ],
            'regular' => [
                'id' => 2,
                'name' => 'Regular User',
                'email' => 'user@example.com',
            ],
        ];
    }
}
