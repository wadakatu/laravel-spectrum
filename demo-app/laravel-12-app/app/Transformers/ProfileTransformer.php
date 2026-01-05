<?php

declare(strict_types=1);

namespace App\Transformers;

use League\Fractal\TransformerAbstract;

/**
 * Transforms Profile data for API responses.
 *
 * A simple transformer demonstrating basic transformation
 * without includes.
 */
class ProfileTransformer extends TransformerAbstract
{
    /**
     * Transform profile data into an array.
     *
     * @param  object  $profile
     * @return array<string, mixed>
     */
    public function transform($profile): array
    {
        return [
            'id' => (int) ($profile->id ?? 0),
            'bio' => (string) ($profile->bio ?? ''),
            'avatar_url' => $profile->avatar_url ?? null,
            'website' => $profile->website ?? null,
            'location' => $profile->location ?? null,
            'social_links' => [
                'twitter' => $profile->twitter ?? null,
                'github' => $profile->github ?? null,
                'linkedin' => $profile->linkedin ?? null,
            ],
        ];
    }
}
