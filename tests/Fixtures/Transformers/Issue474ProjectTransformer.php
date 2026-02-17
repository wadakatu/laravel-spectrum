<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Fixtures\Transformers;

use League\Fractal\TransformerAbstract;

class Issue474ProjectTransformer extends TransformerAbstract
{
    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     status: int,
     *     current_plan: array{plan: string, interval: string, price: int},
     *     project_users: array<array{id: string, email: string, role: string}>,
     *     tags: string[],
     *     roles: list<string>,
     *     avatar: string|null,
     *     bio: ?string,
     *     is_owner: bool,
     *     created_at: string,
     * }
     */
    public function transform($project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'status' => $project->status,
            'current_plan' => $project->current_plan,
            'project_users' => $project->project_users,
            'tags' => $project->tags,
            'roles' => $project->roles,
            'avatar' => $project->avatar,
            'bio' => $project->bio,
            'is_owner' => $project->is_owner,
            'created_at' => $project->created_at,
        ];
    }
}
