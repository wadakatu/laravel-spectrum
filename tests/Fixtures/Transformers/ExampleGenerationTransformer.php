<?php

namespace LaravelSpectrum\Tests\Fixtures\Transformers;

use League\Fractal\TransformerAbstract;

/**
 * Transformer to test various example generation patterns.
 */
class ExampleGenerationTransformer extends TransformerAbstract
{
    public function transform($item)
    {
        return [
            // Integer patterns
            'id' => (int) $item->id,
            'user_id' => (int) $item->user_id,
            'count' => (int) $item->count,
            'total_count' => (int) $item->total_count,
            'amount' => (int) $item->amount, // default integer case

            // Boolean pattern
            'is_active' => (bool) $item->is_active,

            // Array pattern - explicit array cast
            'tags' => (array) $item->tags,

            // Object pattern (nested array)
            'metadata' => [
                'key' => 'value',
            ],

            // String patterns for example generation
            'email' => $item->email,
            'name' => $item->name,
            'title' => $item->title,
            'body' => $item->body,
            'status' => $item->status,
            'type' => $item->type,
            'avatar_url' => $item->avatar_url,
            'image_url' => $item->image_url,
            'created_at' => $item->created_at->toIso8601String(),
            'updated_at' => $item->updated_at->toIso8601String(),
            'description' => $item->description, // default string case

            // Null coalescing for nullable detection
            'nickname' => $item->nickname ?? null,
        ];
    }
}
