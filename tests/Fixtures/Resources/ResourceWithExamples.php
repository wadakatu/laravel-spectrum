<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Fixtures\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use LaravelSpectrum\Contracts\HasExamples;

/**
 * Resource implementing HasExamples interface for testing.
 */
class ResourceWithExamples extends JsonResource implements HasExamples
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }

    public function getExample(): array
    {
        return [
            'id' => 1,
            'name' => 'Example User',
        ];
    }

    public function getExamples(): array
    {
        return [
            'default' => [
                'id' => 1,
                'name' => 'Default Example',
            ],
            'admin' => [
                'id' => 2,
                'name' => 'Admin Example',
            ],
        ];
    }
}
