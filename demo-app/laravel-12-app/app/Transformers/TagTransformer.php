<?php

declare(strict_types=1);

namespace App\Transformers;

use League\Fractal\TransformerAbstract;

/**
 * Transforms Tag data for API responses.
 *
 * A simple transformer for tag data.
 */
class TagTransformer extends TransformerAbstract
{
    /**
     * Transform tag data into an array.
     *
     * @param  object  $tag
     * @return array<string, mixed>
     */
    public function transform($tag): array
    {
        return [
            'id' => (int) ($tag->id ?? 0),
            'name' => (string) ($tag->name ?? ''),
            'slug' => (string) ($tag->slug ?? ''),
            'posts_count' => (int) ($tag->posts_count ?? 0),
        ];
    }
}
