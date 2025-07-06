<?php

namespace LaravelPrism\Tests\Fixtures\Transformers;

use League\Fractal\TransformerAbstract;

class ComplexTransformer extends TransformerAbstract
{
    protected $availableIncludes = [
        'nested_resource',
        'related_items',
        'metadata',
    ];

    protected $defaultIncludes = [
        'metadata',
    ];

    public function transform($model)
    {
        return [
            'id' => $model->id,
            'type' => $model->type,
            'data' => [
                'primary' => $model->primary_data,
                'secondary' => $model->secondary_data,
                'attributes' => $model->attributes ?? [],
            ],
            'settings' => json_decode($model->settings, true),
            'status' => $model->status,
            'flags' => [
                'is_active' => (bool) $model->is_active,
                'is_featured' => (bool) $model->is_featured,
                'is_archived' => (bool) $model->is_archived,
            ],
            'metrics' => [
                'views' => (int) $model->view_count,
                'likes' => (int) $model->like_count,
                'shares' => (int) $model->share_count,
            ],
            'timestamps' => [
                'created_at' => $model->created_at->toIso8601String(),
                'updated_at' => $model->updated_at->toIso8601String(),
                'processed_at' => $model->processed_at ? $model->processed_at->toIso8601String() : null,
            ],
        ];
    }

    public function includeNestedResource($model)
    {
        if ($model->nestedResource) {
            return $this->item($model->nestedResource, new NestedResourceTransformer);
        }

        return $this->null();
    }

    public function includeRelatedItems($model)
    {
        return $this->collection($model->relatedItems, new RelatedItemTransformer);
    }

    public function includeMetadata($model)
    {
        return $this->item($model->metadata, new MetadataTransformer);
    }
}
