<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Fixtures\Controllers;

use LaravelSpectrum\Tests\Fixtures\Requests\NullableFieldsRequest;

class NullableTestController
{
    public function store(NullableFieldsRequest $request): array
    {
        return ['id' => 1];
    }
}
