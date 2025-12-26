<?php

namespace LaravelSpectrum\Tests\Fixtures\Transformers;

use League\Fractal\TransformerAbstract;

/**
 * Transformer with available includes but missing include methods.
 */
class MissingIncludeMethodTransformer extends TransformerAbstract
{
    protected $availableIncludes = [
        'author', // No includeAuthor method exists
    ];

    public function transform($item)
    {
        return [
            'id' => (int) $item->id,
        ];
    }
}
