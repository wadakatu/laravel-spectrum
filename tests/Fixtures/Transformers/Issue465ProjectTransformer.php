<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Fixtures\Transformers;

use LaravelSpectrum\Tests\Fixtures\Models\Issue465Project;
use League\Fractal\TransformerAbstract;

class Issue465ProjectTransformer extends TransformerAbstract
{
    /**
     * @param  Issue465Project  $project
     */
    public function transform($project): array
    {
        $notificationCodes = [];

        if ($project->should_notify_a) {
            $notificationCodes[] = 'code_a';
        }

        if ($project->should_notify_b) {
            $notificationCodes[] = 'code_b';
        }

        return [
            'project_users' => $this->transformProjectUsers($project),
            'verified' => $project->publish->verified ?? 0,
            'published' => $project->publish->published ?? 0,
            'notification_codes' => $notificationCodes,
        ];
    }

    /**
     * @param  Issue465Project  $project
     * @return array<int, array{id: int, email: mixed, name: mixed, role_display_name: mixed}>
     */
    private function transformProjectUsers($project): array
    {
        return array_map(function ($user): array {
            return [
                'id' => (int) $user['id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'role_display_name' => $user['role_display_name'],
            ];
        }, $project->users->toArray());
    }
}
