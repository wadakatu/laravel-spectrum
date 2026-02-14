<?php

declare(strict_types=1);

namespace App\Transformers;

use League\Fractal\TransformerAbstract;

/**
 * Transforms Comment data for API responses.
 *
 * Demonstrates a transformer with optional author include.
 */
class CommentTransformer extends TransformerAbstract
{
    /**
     * Relations that can be included if requested.
     *
     * @var array<string>
     */
    protected $availableIncludes = ['author'];

    /**
     * Transform comment data into an array.
     *
     * @param  object  $comment
     * @return array<string, mixed>
     */
    public function transform($comment): array
    {
        return [
            'id' => (int) ($comment->id ?? 0),
            'body' => (string) ($comment->body ?? $comment->content ?? ''),
            'is_approved' => (bool) ($comment->is_approved ?? true),
            'likes_count' => (int) ($comment->likes_count ?? 0),
            'created_at' => $comment->created_at?->toIso8601String() ?? null,
        ];
    }

    /**
     * Include comment's author.
     *
     * @param  object  $comment
     * @return \League\Fractal\Resource\Item|\League\Fractal\Resource\NullResource
     */
    public function includeAuthor($comment)
    {
        $author = $comment->user ?? $comment->author ?? null;

        if (! $author) {
            return $this->null();
        }

        return $this->item($author, new UserTransformer);
    }
}
