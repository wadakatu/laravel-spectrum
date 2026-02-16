<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Fixtures\Transformers;

use League\Fractal\TransformerAbstract;

class Issue467ProjectTransformer extends TransformerAbstract
{
    public function transform($project): array
    {
        return [
            'project_users' => self::transformProjectUsers($project->project_users),
        ];
    }

    /**
     * @return array<int, array{id: int, email: mixed, is_invited: bool}>
     */
    private static function transformProjectUsers($projectUsers): array
    {
        return array_map(
            static function ($projectUser): array {
                return [
                    'id' => (int) $projectUser['id'],
                    'email' => $projectUser['email'],
                    'is_invited' => false,
                ];
            },
            $projectUsers
        );
    }
}
