<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Fixtures\Transformers;

use League\Fractal\TransformerAbstract;

class Issue474ProjectTransformer extends TransformerAbstract
{
    /**
     * @return array{
     *     id: string,
     *     project_id: int,
     *     name: string,
     *     status: int,
     *     budget: number,
     *     current_plan: array{plan: string, interval: string, price: int},
     *     project_users: array<array{id: string, email: string, role: string}>,
     *     tags: string[],
     *     roles: list<string>,
     *     legacy_list: list,
     *     raw_items: array,
     *     profile: object,
     *     due_date: date,
     *     updated_at_custom: datetime,
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
            'project_id' => $project->project_id,
            'name' => $project->name,
            'status' => $project->status,
            'budget' => $project->budget,
            'current_plan' => $project->current_plan,
            'project_users' => $project->project_users,
            'tags' => $project->tags,
            'roles' => $project->roles,
            'legacy_list' => $project->legacy_list,
            'raw_items' => $project->raw_items,
            'profile' => $project->profile,
            'due_date' => $project->due_date,
            'updated_at_custom' => $project->updated_at_custom,
            'avatar' => $project->avatar,
            'bio' => $project->bio,
            'is_owner' => $project->is_owner,
            'created_at' => $project->created_at,
        ];
    }
}
