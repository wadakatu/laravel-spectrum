<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Fixtures\Transformers;

use League\Fractal\TransformerAbstract;

class VariableReturnTransformer extends TransformerAbstract
{
    public function transform($item): array
    {
        $payload = [
            'id' => (int) $item->id,
            'name' => $item->name,
            'is_active' => $item->is_active,
        ];

        return $payload;
    }
}
