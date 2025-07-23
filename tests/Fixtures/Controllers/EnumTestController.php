<?php

namespace LaravelSpectrum\Tests\Fixtures\Controllers;

use LaravelSpectrum\Tests\Fixtures\Enums\PriorityEnum;
use LaravelSpectrum\Tests\Fixtures\Enums\StatusEnum;

class EnumTestController
{
    public function store(StatusEnum $status, PriorityEnum $priority): array
    {
        return [
            'status' => $status->value,
            'priority' => $priority->value,
        ];
    }

    public function getStatus(): StatusEnum
    {
        return StatusEnum::ACTIVE;
    }

    public function update(string $id, ?StatusEnum $status = null): void
    {
        // Method implementation
    }
}
