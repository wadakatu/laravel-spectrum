<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Fixtures\Transformers;

use League\Fractal\TransformerAbstract;

/**
 * A minimal transformer without includes for testing edge cases.
 */
class MinimalTransformer extends TransformerAbstract
{
    // No availableIncludes or defaultIncludes

    public function transform($item): array
    {
        // Returns empty array - no properties to extract
        return [];
    }
}
