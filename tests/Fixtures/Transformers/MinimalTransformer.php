<?php

namespace LaravelSpectrum\Tests\Fixtures\Transformers;

use League\Fractal\TransformerAbstract;

/**
 * A minimal transformer without includes for testing edge cases.
 */
class MinimalTransformer extends TransformerAbstract
{
    // No availableIncludes or defaultIncludes

    public function transform($item)
    {
        // Return non-array to test edge case
        return [];
    }
}
