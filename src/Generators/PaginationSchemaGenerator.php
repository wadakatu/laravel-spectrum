<?php

namespace LaravelSpectrum\Generators;

class PaginationSchemaGenerator
{
    /**
     * Generate pagination schema based on type
     */
    public function generate(string $paginationType, array $dataSchema): array
    {
        return match ($paginationType) {
            'length_aware' => $this->generateLengthAwarePaginatorSchema($dataSchema),
            'simple' => $this->generateSimplePaginatorSchema($dataSchema),
            'cursor' => $this->generateCursorPaginatorSchema($dataSchema),
            default => $dataSchema
        };
    }

    /**
     * Generate schema for LengthAwarePaginator
     */
    private function generateLengthAwarePaginatorSchema(array $dataSchema): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'data' => [
                    'type' => 'array',
                    'items' => $dataSchema,
                ],
                'current_page' => [
                    'type' => 'integer',
                    'example' => 1,
                ],
                'first_page_url' => [
                    'type' => 'string',
                    'format' => 'uri',
                ],
                'from' => [
                    'type' => 'integer',
                    'nullable' => true,
                ],
                'last_page' => [
                    'type' => 'integer',
                ],
                'last_page_url' => [
                    'type' => 'string',
                    'format' => 'uri',
                ],
                'links' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'url' => [
                                'type' => 'string',
                                'nullable' => true,
                                'format' => 'uri',
                            ],
                            'label' => [
                                'type' => 'string',
                            ],
                            'active' => [
                                'type' => 'boolean',
                            ],
                        ],
                    ],
                ],
                'next_page_url' => [
                    'type' => 'string',
                    'nullable' => true,
                    'format' => 'uri',
                ],
                'path' => [
                    'type' => 'string',
                    'format' => 'uri',
                ],
                'per_page' => [
                    'type' => 'integer',
                ],
                'prev_page_url' => [
                    'type' => 'string',
                    'nullable' => true,
                    'format' => 'uri',
                ],
                'to' => [
                    'type' => 'integer',
                    'nullable' => true,
                ],
                'total' => [
                    'type' => 'integer',
                ],
            ],
        ];
    }

    /**
     * Generate schema for SimplePaginator
     */
    private function generateSimplePaginatorSchema(array $dataSchema): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'data' => [
                    'type' => 'array',
                    'items' => $dataSchema,
                ],
                'first_page_url' => [
                    'type' => 'string',
                    'format' => 'uri',
                ],
                'from' => [
                    'type' => 'integer',
                    'nullable' => true,
                ],
                'next_page_url' => [
                    'type' => 'string',
                    'nullable' => true,
                    'format' => 'uri',
                ],
                'path' => [
                    'type' => 'string',
                    'format' => 'uri',
                ],
                'per_page' => [
                    'type' => 'integer',
                ],
                'prev_page_url' => [
                    'type' => 'string',
                    'nullable' => true,
                    'format' => 'uri',
                ],
                'to' => [
                    'type' => 'integer',
                    'nullable' => true,
                ],
            ],
        ];
    }

    /**
     * Generate schema for CursorPaginator
     */
    private function generateCursorPaginatorSchema(array $dataSchema): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'data' => [
                    'type' => 'array',
                    'items' => $dataSchema,
                ],
                'path' => [
                    'type' => 'string',
                    'format' => 'uri',
                ],
                'per_page' => [
                    'type' => 'integer',
                ],
                'next_cursor' => [
                    'type' => 'string',
                    'nullable' => true,
                ],
                'next_page_url' => [
                    'type' => 'string',
                    'nullable' => true,
                    'format' => 'uri',
                ],
                'prev_cursor' => [
                    'type' => 'string',
                    'nullable' => true,
                ],
                'prev_page_url' => [
                    'type' => 'string',
                    'nullable' => true,
                    'format' => 'uri',
                ],
            ],
        ];
    }
}
