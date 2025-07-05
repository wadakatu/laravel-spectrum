<?php

namespace LaravelPrism\Generators;

class SchemaGenerator
{
    /**
     * パラメータからスキーマを生成
     */
    public function generateFromParameters(array $parameters): array
    {
        $properties = [];
        $required   = [];

        foreach ($parameters as $parameter) {
            $properties[$parameter['name']] = [
                'type'        => $parameter['type'],
                'description' => $parameter['description'] ?? null,
                'example'     => $parameter['example'] ?? null,
            ];

            if ($parameter['required']) {
                $required[] = $parameter['name'];
            }
        }

        $schema = [
            'type'       => 'object',
            'properties' => $properties,
        ];

        if (! empty($required)) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /**
     * リソース構造からスキーマを生成
     */
    public function generateFromResource(array $resourceStructure): array
    {
        $properties = [];

        foreach ($resourceStructure as $field => $info) {
            $properties[$field] = [
                'type'    => $info['type'],
                'example' => $info['example'],
            ];
        }

        return [
            'type'       => 'object',
            'properties' => $properties,
        ];
    }
}
