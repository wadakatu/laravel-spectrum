<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Fixtures\Transformers;

use LaravelSpectrum\Tests\Fixtures\Models\Issue458Project;
use League\Fractal\TransformerAbstract;

class Issue458ProjectTransformer extends TransformerAbstract
{
    /**
     * @param  Issue458Project  $project
     */
    public function transform($project): array
    {
        $userId = 1;

        return [
            'id' => (int) $project->id,
            'name' => $project->name,
            'current_plan' => $this->currentPlan($project),
            'next_plan' => $this->nextPlan($project),
            'project_users' => $this->projectUsers($project),
            'inline_project_users' => [
                [
                    'id' => (int) $project->owner_id,
                    'name' => 'InlineOwner',
                ],
            ],
            'external_plan' => $project->currentPlan(),
            'notification_codes' => $project->notification_codes,
            'is_owner' => $userId === $project->user_id,
            'verified' => $project->verified,
        ];
    }

    private function currentPlan($project): array
    {
        return [
            'plan' => $project->plan,
            'interval' => 'monthly',
            'currency' => 'jpy',
            'price' => 5000,
            'discount' => [
                'coupon_amount' => 1000,
                'proration_amount' => 500,
            ],
        ];
    }

    private function nextPlan($project): array
    {
        $plan = [
            'plan' => $project->next_plan,
            'interval' => 'yearly',
            'currency' => 'jpy',
            'price' => 50000,
        ];

        return $plan;
    }

    private function projectUsers($project): array
    {
        return [
            [
                'id' => (int) $project->owner_id,
                'name' => 'Owner',
            ],
        ];
    }
}
