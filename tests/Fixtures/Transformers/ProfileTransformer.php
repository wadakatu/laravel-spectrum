<?php

namespace LaravelSpectrum\Tests\Fixtures\Transformers;

use League\Fractal\TransformerAbstract;

class ProfileTransformer extends TransformerAbstract
{
    public function transform($profile)
    {
        return [
            'bio' => $profile->bio,
            'avatar_url' => $profile->avatar_url,
            'location' => $profile->location,
            'website' => $profile->website,
        ];
    }
}
