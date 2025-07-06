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
        $required = [];

        foreach ($parameters as $parameter) {
            if (! isset($parameter['name'])) {
                continue;
            }

            $properties[$parameter['name']] = [
                'type' => $parameter['type'] ?? 'string',
                'description' => $parameter['description'] ?? null,
                'example' => $parameter['example'] ?? null,
            ];

            if ($parameter['required'] ?? false) {
                $required[] = $parameter['name'];
            }
        }

        $schema = [
            'type' => 'object',
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
                'type' => $info['type'],
                'example' => $info['example'],
            ];
        }

        return [
            'type' => 'object',
            'properties' => $properties,
        ];
    }

    /**
     * Generate schema from conditional parameters
     */
    public function generateFromConditionalParameters(array $parameters): array
    {
        // Group parameters by HTTP method
        $groupedByMethod = $this->groupParametersByHttpMethod($parameters);

        if (count($groupedByMethod) <= 1) {
            // No conditions or single condition - generate regular schema
            return $this->generateFromParameters($parameters);
        }

        // Generate oneOf schema
        $schemas = [];

        foreach ($groupedByMethod as $method => $params) {
            $schema = $this->generateFromParameters($params);
            $schema['title'] = "{$method} Request";
            $schemas[] = $schema;
        }

        return [
            'oneOf' => $schemas,
        ];
    }

    /**
     * Group parameters by HTTP method from conditional rules
     */
    private function groupParametersByHttpMethod(array $parameters): array
    {
        $grouped = [];
        $hasConditions = false;

        foreach ($parameters as $param) {
            if (! isset($param['conditional_rules']) || empty($param['conditional_rules'])) {
                continue;
            }

            $hasConditions = true;

            foreach ($param['conditional_rules'] as $condRule) {
                $method = $this->extractHttpMethodFromConditions($condRule['conditions']);

                if (! $method) {
                    $method = 'DEFAULT';
                }

                if (! isset($grouped[$method])) {
                    $grouped[$method] = [];
                }

                // Check if this parameter already exists for this method
                $exists = false;
                foreach ($grouped[$method] as $existingParam) {
                    if ($existingParam['name'] === $param['name']) {
                        $exists = true;
                        break;
                    }
                }

                if (! $exists) {
                    $grouped[$method][] = [
                        'name' => $param['name'],
                        'type' => $param['type'],
                        'required' => $this->isRequired($condRule['rules']),
                        'description' => $param['description'],
                        'example' => $param['example'] ?? null,
                        'validation' => $condRule['rules'],
                    ];
                }
            }
        }

        // If no conditions were found, return parameters as single group
        if (! $hasConditions) {
            return ['all' => $parameters];
        }

        return $grouped;
    }

    /**
     * Extract HTTP method from conditions array
     */
    private function extractHttpMethodFromConditions(array $conditions): ?string
    {
        foreach ($conditions as $condition) {
            if (isset($condition['type']) && $condition['type'] === 'http_method' && isset($condition['method'])) {
                return $condition['method'];
            }
        }

        return null;
    }

    /**
     * Check if rules include required validation
     */
    private function isRequired(array $rules): bool
    {
        foreach ($rules as $rule) {
            $ruleName = is_string($rule) ? explode(':', $rule)[0] : '';
            if (in_array($ruleName, ['required', 'required_if', 'required_unless', 'required_with', 'required_without'])) {
                return true;
            }
        }

        return false;
    }
}
