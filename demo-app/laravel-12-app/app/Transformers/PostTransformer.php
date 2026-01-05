<?php

declare(strict_types=1);

namespace App\Transformers;

use App\Models\Post;
use League\Fractal\TransformerAbstract;

/**
 * Transforms Post model data for API responses.
 *
 * Demonstrates nested transformers with:
 * - Basic transform method with type casting
 * - Default includes (author always included)
 * - Available includes (comments, tags)
 * - Nested object structure
 */
class PostTransformer extends TransformerAbstract
{
    /**
     * Relations that can be included if requested.
     *
     * @var array<string>
     */
    protected array $availableIncludes = ['comments', 'tags'];

    /**
     * Relations that are included by default.
     *
     * @var array<string>
     */
    protected array $defaultIncludes = ['author'];

    /**
     * Transform a Post model into an array.
     *
     * @return array<string, mixed>
     */
    public function transform(Post $post): array
    {
        return [
            'id' => (int) $post->id,
            'title' => (string) $post->title,
            'slug' => (string) ($post->slug ?? ''),
            'content' => (string) ($post->content ?? ''),
            'excerpt' => $this->generateExcerpt($post),
            'is_published' => (bool) ($post->is_published ?? false),
            'meta' => [
                'views_count' => (int) ($post->views_count ?? 0),
                'likes_count' => (int) ($post->likes_count ?? 0),
                'reading_time' => $this->calculateReadingTime($post),
            ],
            'published_at' => $post->published_at?->toIso8601String(),
            'created_at' => $post->created_at?->toIso8601String(),
            'updated_at' => $post->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Include post's author.
     *
     * @return \League\Fractal\Resource\Item|\League\Fractal\Resource\NullResource
     */
    public function includeAuthor(Post $post)
    {
        $author = $post->user ?? $post->author ?? null;

        if (! $author) {
            return $this->null();
        }

        return $this->item($author, new UserTransformer);
    }

    /**
     * Include post's comments.
     *
     * @return \League\Fractal\Resource\Collection
     */
    public function includeComments(Post $post)
    {
        $comments = $post->comments ?? collect();

        return $this->collection($comments, new CommentTransformer);
    }

    /**
     * Include post's tags.
     *
     * @return \League\Fractal\Resource\Collection
     */
    public function includeTags(Post $post)
    {
        $tags = $post->tags ?? collect();

        return $this->collection($tags, new TagTransformer);
    }

    /**
     * Generate an excerpt from the post content.
     */
    private function generateExcerpt(Post $post): string
    {
        $content = $post->content ?? '';

        return mb_strlen($content) > 150
            ? mb_substr($content, 0, 150).'...'
            : $content;
    }

    /**
     * Calculate estimated reading time in minutes.
     */
    private function calculateReadingTime(Post $post): int
    {
        $content = $post->content ?? '';
        $wordCount = str_word_count(strip_tags($content));

        return (int) max(1, ceil($wordCount / 200));
    }
}
