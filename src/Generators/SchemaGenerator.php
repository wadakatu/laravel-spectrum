<?php

namespace LaravelSpectrum\Generators;

use LaravelSpectrum\DTO\ConditionResult;
use LaravelSpectrum\DTO\ResourceInfo;
use LaravelSpectrum\Generators\Support\SchemaPropertyMapper;
use LaravelSpectrum\Support\TypeInference;

class SchemaGenerator
{
    protected FileUploadSchemaGenerator $fileUploadSchemaGenerator;

    protected TypeInference $typeInference;

    protected SchemaPropertyMapper $propertyMapper;

    public function __construct(
        ?FileUploadSchemaGenerator $fileUploadSchemaGenerator = null,
        ?TypeInference $typeInference = null,
        ?SchemaPropertyMapper $propertyMapper = null
    ) {
        $this->fileUploadSchemaGenerator = $fileUploadSchemaGenerator ?? new FileUploadSchemaGenerator;
        $this->typeInference = $typeInference ?? new TypeInference;
        $this->propertyMapper = $propertyMapper ?? new SchemaPropertyMapper;
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

            $property = $this->propertyMapper->mapType($parameter);
            $property = $this->propertyMapper->mapAll($parameter, $property);

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
        $properties = [];
        $required = [];

        // Process normal fields
        foreach ($normalFields as $field) {
            if (! isset($field['name'])) {
                continue;
            }

            $fieldName = $this->normalizeFieldName($field['name']);

            $property = $this->propertyMapper->mapType($field);
            $property = $this->propertyMapper->mapSimpleProperties($field, $property);
            $property = $this->propertyMapper->mapConstraints($field, $property);
            $property = $this->propertyMapper->mapEnum($field, $property);

            $properties[$fieldName] = $property;

            if ($field['required'] ?? false) {
                $required[] = $fieldName;
            }
        }

        // Process file fields
        foreach ($fileFields as $field) {
            if (! isset($field['name'])) {
                continue;
            }

            $fieldName = $this->normalizeFieldName($field['name']);
            $isArrayField = $this->isArrayField($field['name']);

            if ($isArrayField) {
                // Handle array file uploads (e.g., photos.*, documents.*)
                $baseName = $this->getArrayBaseName($field['name']);

                $properties[$baseName] = [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                        'format' => 'binary',
                    ],
                ];

                if (isset($field['description'])) {
                    $properties[$baseName]['description'] = $field['description'];
                }

                // Add constraints
                if (isset($field['file_info'])) {
                    if (isset($field['file_info']['max_size'])) {
                        $properties[$baseName]['items']['maxSize'] = $field['file_info']['max_size'];
                    }
                    if (! empty($field['file_info']['mime_types'])) {
                        $properties[$baseName]['items']['contentMediaType'] = implode(', ', $field['file_info']['mime_types']);
                    }
                }
            } else {
                // Single file upload
                $properties[$fieldName] = [
                    'type' => 'string',
                    'format' => 'binary',
                ];

                if (isset($field['description'])) {
                    $properties[$fieldName]['description'] = $field['description'];
                }

                // Add constraints as extensions
                if (isset($field['file_info'])) {
                    if (isset($field['file_info']['max_size'])) {
                        $properties[$fieldName]['maxSize'] = $field['file_info']['max_size'];
                    }
                    if (! empty($field['file_info']['mime_types'])) {
                        $properties[$fieldName]['contentMediaType'] = implode(', ', $field['file_info']['mime_types']);
                    }
                }
            }

            if ($field['required'] ?? false) {
                $required[] = $isArrayField ? $this->getArrayBaseName($field['name']) : $fieldName;
            }
        }

        return [
            'content' => [
                'multipart/form-data' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => $properties,
                        'required' => array_unique($required),
                    ],
                ],
            ],
        ];
    }

    /**
     * リソース構造からスキーマを生成
     */
    public function generateFromResource(ResourceInfo $resourceInfo): array
    {
        $properties = [];

        foreach ($resourceInfo->properties as $field => $info) {
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
     *
     * @param  array<ConditionResult>  $conditions
     */
    private function extractHttpMethodFromConditions(array $conditions): ?string
    {
        foreach ($conditions as $condition) {
            if ($condition->isHttpMethod() && $condition->method !== null) {
                return $condition->method;
            }
        }

        return null;
    }

    /**
     * Generate conditional schema using oneOf
     */
    public function generateConditionalSchema(array $conditionalRules, array $parameters): array
    {
        if (empty($conditionalRules['rules_sets']) || count($conditionalRules['rules_sets']) <= 1) {
            // No conditions or single rule set - generate normal schema
            return $this->generateFromParameters($parameters);
        }

        $schemas = [];

        foreach ($conditionalRules['rules_sets'] as $ruleSet) {
            $schema = $this->generateSchemaForRuleSet($ruleSet);

            // Add condition description
            $conditionDesc = $this->generateConditionDescription($ruleSet['conditions']);
            if ($conditionDesc) {
                $schema['description'] = $conditionDesc;
            }

            $schemas[] = $schema;
        }

        // Return oneOf schema
        return [
            'oneOf' => $schemas,
            'discriminator' => [
                'propertyName' => '_condition',
                'mapping' => $this->generateDiscriminatorMapping($conditionalRules['rules_sets']),
            ],
        ];
    }

    /**
     * Generate schema for a specific rule set
     */
    private function generateSchemaForRuleSet(array $ruleSet): array
    {
        $properties = [];
        $required = [];

        foreach ($ruleSet['rules'] as $field => $rules) {
            $rulesList = is_string($rules) ? explode('|', $rules) : $rules;

            $property = [
                'type' => $this->typeInference->inferFromRules($rulesList),
            ];

            // Add constraints
            foreach ($rulesList as $rule) {
                $this->applyRuleConstraints($property, $rule);
            }

            $properties[$field] = $property;

            if ($this->isFieldRequired($rulesList)) {
                $required[] = $field;
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
     * Generate human-readable condition description
     *
     * @param  array<ConditionResult>  $conditions
     */
    private function generateConditionDescription(array $conditions): string
    {
        if (empty($conditions)) {
            return 'Default validation rules';
        }

        $parts = [];

        foreach ($conditions as $condition) {
            if ($condition->isHttpMethod()) {
                $parts[] = "When HTTP method is {$condition->method}";
            } elseif ($condition->isUserCheck()) {
                $parts[] = "When user {$condition->method}()";
            } elseif ($condition->isRequestField()) {
                $field = $condition->field ?? 'field';
                $parts[] = "When request {$condition->check} '{$field}'";
            } elseif ($condition->isElseBranch()) {
                $parts[] = 'Otherwise';
            } else {
                $expression = $condition->expression ?? 'custom condition';
                $parts[] = "When {$expression}";
            }
        }

        return implode(' AND ', $parts);
    }

    /**
     * Generate discriminator mapping for oneOf schemas
     */
    private function generateDiscriminatorMapping(array $ruleSets): array
    {
        $mapping = [];

        foreach ($ruleSets as $index => $ruleSet) {
            $key = $this->generateConditionKey($ruleSet['conditions']);
            $mapping[$key] = "#/oneOf/{$index}";
        }

        return $mapping;
    }

    /**
     * Generate unique key for condition set
     *
     * @param  array<ConditionResult>  $conditions
     */
    private function generateConditionKey(array $conditions): string
    {
        if (empty($conditions)) {
            return 'default';
        }

        $parts = [];
        foreach ($conditions as $condition) {
            if ($condition->isHttpMethod() && $condition->method !== null) {
                $parts[] = strtolower($condition->method);
            } elseif ($condition->isElseBranch()) {
                $parts[] = 'else';
            } elseif ($condition->isUserCheck() && $condition->method !== null) {
                $parts[] = 'user_'.strtolower($condition->method);
            } elseif ($condition->isRequestField()) {
                $field = $condition->field ?? 'field';
                $check = $condition->check ?? 'has';
                $parts[] = 'request_'.strtolower($check).'_'.strtolower($field);
            } else {
                $parts[] = substr(md5($condition->expression ?? 'unknown'), 0, 8);
            }
        }

        return implode('_', $parts);
    }

    /**
     * Apply rule constraints to property schema
     */
    private function applyRuleConstraints(array &$property, string|object $rule): void
    {
        if (! is_string($rule)) {
            return;
        }

        $parts = explode(':', $rule, 2);
        $ruleName = $parts[0];
        $ruleValue = $parts[1] ?? null;

        switch ($ruleName) {
            case 'min':
                if ($property['type'] === 'string') {
                    $property['minLength'] = (int) $ruleValue;
                } else {
                    $property['minimum'] = (int) $ruleValue;
                }
                break;
            case 'max':
                if ($property['type'] === 'string') {
                    $property['maxLength'] = (int) $ruleValue;
                } else {
                    $property['maximum'] = (int) $ruleValue;
                }
                break;
            case 'email':
                $property['format'] = 'email';
                break;
            case 'url':
                $property['format'] = 'uri';
                break;
            case 'date':
                $property['format'] = 'date';
                break;
            case 'datetime':
                $property['format'] = 'date-time';
                break;
            case 'in':
                if ($ruleValue) {
                    $property['enum'] = explode(',', $ruleValue);
                }
                break;
            case 'regex':
                if ($ruleValue) {
                    $property['pattern'] = $ruleValue;
                }
                break;
        }
    }

    /**
     * Check if field is required based on rules
     */
    private function isFieldRequired(array $rules): bool
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
     * Check if rules include required validation
     */
    private function isRequired(array $rules): bool
    {
        return $this->isFieldRequired($rules);
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
            // ネストしたプロパティの処理
            if (isset($property['properties'])) {
                $propSchema = $this->convertFractalPropertiesToSchema($property['properties']);
                $propSchema['type'] = 'object';
            } else {
                $propSchema = $this->propertyMapper->mapType($property);
                $propSchema = $this->propertyMapper->mapSimpleProperties($property, $propSchema);
                $propSchema = $this->propertyMapper->mapBooleanProperties($property, $propSchema);
            }

            $schema['properties'][$key] = $propSchema;
        }

        return $schema;
    }

    /**
     * Normalize field names (e.g., photos.* -> photos)
     */
    private function normalizeFieldName(string $name): string
    {
        // Remove array notation
        return str_replace(['.*', '[*]', '[]'], '', $name);
    }

    /**
     * Check if field is an array field
     */
    private function isArrayField(string $name): bool
    {
        return str_contains($name, '.*') || str_contains($name, '[*]') || str_contains($name, '[]');
    }

    /**
     * Get base name for array fields
     */
    private function getArrayBaseName(string $name): string
    {
        // Remove array notations: .*, [*], []
        $name = preg_replace('/\.\*$/', '', $name);
        $name = preg_replace('/\[\*?\]$/', '', $name);

        return $name;
    }
}
