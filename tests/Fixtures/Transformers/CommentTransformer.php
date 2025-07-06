<?php

namespace LaravelPrism\Tests\Fixtures\Transformers;

use League\Fractal\TransformerAbstract;

class CommentTransformer extends TransformerAbstract
{
    public function transform($comment)
    {
        return [
            'id' => (int) $comment->id,
            'body' => $comment->body,
            'author_name' => $comment->author_name,
            'created_at' => $comment->created_at->toIso8601String(),
        ];
    }
}
