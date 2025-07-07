<?php

namespace LaravelSpectrum\Tests\Fixtures\Transformers;

use League\Fractal\TransformerAbstract;

class PostTransformer extends TransformerAbstract
{
    protected $availableIncludes = [
        'author',
        'comments',
    ];

    public function transform($post)
    {
        return [
            'id' => (int) $post->id,
            'title' => $post->title,
            'body' => $post->body,
            'published_at' => $post->published_at ? $post->published_at->toIso8601String() : null,
            'status' => $post->status,
        ];
    }

    public function includeAuthor($post)
    {
        return $this->item($post->author, new UserTransformer);
    }

    public function includeComments($post)
    {
        return $this->collection($post->comments, new CommentTransformer);
    }
}
