<?php

namespace LaravelSpectrum\Generators;

class SchemaGenerator
{
    protected FileUploadSchemaGenerator $fileUploadSchemaGenerator;

    public function __construct(?FileUploadSchemaGenerator $fileUploadSchemaGenerator = null)
    {
        $this->fileUploadSchemaGenerator = $fileUploadSchemaGenerator ?? new FileUploadSchemaGenerator;
    }

    /**
     * パラメータからスキーマを生成
     */
    public function generateFromParameters(array $parameters): array
    {
        // Check if any parameter is a file upload
        $hasFileUpload = false;
        $fileFields = [];
        $normalFields = [];

        foreach ($parameters as $parameter) {
            if (isset($parameter['type']) && $parameter['type'] === 'file') {
                $hasFileUpload = true;
                $fileFields[] = $parameter;
            } else {
                $normalFields[] = $parameter;
            }
        }

        // If we have file uploads, we need to generate multipart/form-data schema
        if ($hasFileUpload) {
            return $this->generateMultipartSchema($normalFields, $fileFields);
        }

        // Otherwise, generate normal JSON schema
        $properties = [];
        $required = [];

        foreach ($parameters as $parameter) {
            if (! isset($parameter['name'])) {
                continue;
            }

            $property = [
                'type' => $parameter['type'] ?? 'string',
            ];

            // Add optional properties if they exist
            if (isset($parameter['description'])) {
                $property['description'] = $parameter['description'];
            }
            if (isset($parameter['example'])) {
                $property['example'] = $parameter['example'];
            }
            if (isset($parameter['format'])) {
                $property['format'] = $parameter['format'];
            }
            if (isset($parameter['enum'])) {
                // Handle enum from EnumAnalyzer structure
                if (is_array($parameter['enum']) && isset($parameter['enum']['values'])) {
                    $property['enum'] = $parameter['enum']['values'];
                    // Override type if enum has specific type
                    if (isset($parameter['enum']['type'])) {
                        $property['type'] = $parameter['enum']['type'];
                    }
                } else {
                    // Handle simple enum array (for backward compatibility)
                    $property['enum'] = $parameter['enum'];
                }
            }
            if (isset($parameter['minimum'])) {
                $property['minimum'] = $parameter['minimum'];
            }
            if (isset($parameter['maximum'])) {
                $property['maximum'] = $parameter['maximum'];
            }
            if (isset($parameter['minLength'])) {
                $property['minLength'] = $parameter['minLength'];
            }
            if (isset($parameter['maxLength'])) {
                $property['maxLength'] = $parameter['maxLength'];
            }
            if (isset($parameter['pattern'])) {
                $property['pattern'] = $parameter['pattern'];
            }

            $properties[$parameter['name']] = $property;

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
     * Generate multipart/form-data schema
     */
    protected function generateMultipartSchema(array $normalFields, array $fileFields): array
    {
        $normalFieldsFormatted = [];
        $fileFieldsFormatted = [];

        // Format normal fields
        foreach ($normalFields as $field) {
            if (! isset($field['name'])) {
                continue;
            }

            $fieldData = [
                'type' => $field['type'] ?? 'string',
                'required' => $field['required'] ?? false,
            ];

            if (isset($field['description'])) {
                $fieldData['description'] = $field['description'];
            }
            if (isset($field['maxLength'])) {
                $fieldData['maxLength'] = $field['maxLength'];
            }
            if (isset($field['minLength'])) {
                $fieldData['minLength'] = $field['minLength'];
            }
            if (isset($field['enum']) && is_array($field['enum']) && isset($field['enum']['values'])) {
                $fieldData['enum'] = $field['enum']['values'];
                if (isset($field['enum']['type'])) {
                    $fieldData['type'] = $field['enum']['type'];
                }
            }

            $normalFieldsFormatted[$field['name']] = $fieldData;
        }

        // Format file fields
        foreach ($fileFields as $field) {
            if (! isset($field['name'])) {
                continue;
            }

            $fileFieldData = [
                'type' => 'string',
                'format' => 'binary',
                'required' => $field['required'] ?? false,
            ];

            if (isset($field['description'])) {
                $fileFieldData['description'] = $field['description'];
            }

            // Handle array files (multiple uploads)
            if (isset($field['file_info']) && $field['file_info']['multiple']) {
                $fileFieldsFormatted[$field['name']] = [
                    'type' => 'array',
                    'items' => $fileFieldData,
                    'required' => $field['required'] ?? false,
                ];
                if (isset($field['description'])) {
                    $fileFieldsFormatted[$field['name']]['description'] = $field['description'];
                }
            } else {
                $fileFieldsFormatted[$field['name']] = $fileFieldData;
            }
        }

        return $this->fileUploadSchemaGenerator->generateMultipartSchema($normalFieldsFormatted, $fileFieldsFormatted);
    }

    /**
     * リソース構造からスキーマを生成
     */
    public function generateFromResource(array $resourceStructure): array
    {
        $properties = [];

        foreach ($resourceStructure as $field => $info) {
            $schema = [
                'type' => $info['type'],
            ];

            if (isset($info['example'])) {
                $schema['example'] = $info['example'];
            }

            $properties[$field] = $schema;
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

    /**
     * Fractal Transformerからスキーマを生成
     */
    public function generateFromFractal(array $fractalData, bool $isCollection = false, bool $hasPagination = false): array
    {
        $itemSchema = $this->convertFractalPropertiesToSchema($fractalData['properties']);

        // includesを追加
        if (! empty($fractalData['availableIncludes'])) {
            foreach ($fractalData['availableIncludes'] as $includeName => $includeData) {
                $includeSchema = [];

                if ($includeData['collection'] ?? false) {
                    $includeSchema = [
                        'type' => 'array',
                        'items' => ['type' => 'object'],
                    ];
                } else {
                    $includeSchema = ['type' => 'object'];
                }

                // デフォルトincludeかどうかをチェック
                $isDefault = in_array($includeName, $fractalData['defaultIncludes'] ?? []);
                $includeSchema['description'] = $isDefault
                    ? "Default include. Use ?include=$includeName"
                    : "Optional include. Use ?include=$includeName";

                $itemSchema['properties'][$includeName] = $includeSchema;
            }
        }

        // 基本構造を作成
        $schema = [
            'type' => 'object',
            'properties' => [],
        ];

        if ($isCollection) {
            $schema['properties']['data'] = [
                'type' => 'array',
                'items' => $itemSchema,
            ];
        } else {
            $schema['properties']['data'] = $itemSchema;
        }

        // ページネーションメタデータを追加
        if ($hasPagination) {
            $schema['properties']['meta'] = [
                'type' => 'object',
                'properties' => [
                    'pagination' => [
                        'type' => 'object',
                        'properties' => [
                            'total' => ['type' => 'integer', 'example' => 100],
                            'count' => ['type' => 'integer', 'example' => 20],
                            'per_page' => ['type' => 'integer', 'example' => 20],
                            'current_page' => ['type' => 'integer', 'example' => 1],
                            'total_pages' => ['type' => 'integer', 'example' => 5],
                        ],
                    ],
                ],
            ];
        }

        return $schema;
    }

    /**
     * Fractalのプロパティをスキーマ形式に変換
     */
    private function convertFractalPropertiesToSchema(array $properties): array
    {
        $schema = [
            'type' => 'object',
            'properties' => [],
        ];

        foreach ($properties as $key => $property) {
            $propSchema = [
                'type' => $property['type'],
            ];

            if (isset($property['example'])) {
                $propSchema['example'] = $property['example'];
            }

            if (isset($property['nullable']) && $property['nullable']) {
                $propSchema['nullable'] = true;
            }

            // ネストしたプロパティの処理
            if (isset($property['properties'])) {
                $propSchema = $this->convertFractalPropertiesToSchema($property['properties']);
                $propSchema['type'] = 'object';
            }

            $schema['properties'][$key] = $propSchema;
        }

        return $schema;
    }
}
