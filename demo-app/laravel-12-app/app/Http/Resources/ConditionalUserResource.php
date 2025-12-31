<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource using whenLoaded for conditional relationships.
 * Common pattern in OSS projects.
 */
class ConditionalUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,

            // Conditional attributes based on loaded relationships
            'posts' => PostResource::collection($this->whenLoaded('posts')),
            'comments' => $this->whenLoaded('comments'),
            'profile' => $this->whenLoaded('profile'),

            // Conditional counts
            'posts_count' => $this->whenCounted('posts'),
            'comments_count' => $this->whenCounted('comments'),

            // Conditional attributes based on request
            // Fixed in issue #306 - when() and mergeWhen() now work correctly
            'secret_field' => $this->when($request->user()?->isAdmin(), 'secret-value'),

            // mergeWhen pattern - conditionally merge additional fields
            $this->mergeWhen($request->has('full'), [
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
            ]),

            // Always included
            'links' => [
                'self' => "/api/users/{$this->id}",
            ],
        ];
    }
}
