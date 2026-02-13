<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Fixtures\Transformers;

use League\Fractal\TransformerAbstract;

class GetterBasedUserTransformer extends TransformerAbstract
{
    public function transform($user): array
    {
        return [
            'id' => $user->getId(),
            'name' => $user->getName(),
            'email' => $user->getEmail(),
            'avatar' => $user->getAvatar(),
            'language' => $user->getLanguage(),
            'country_code' => $user->getCountryCode(),
            'is_beta_user' => $user->getIsBetaUser(),
            'created_at' => $user->getCreatedAt(),
        ];
    }
}
