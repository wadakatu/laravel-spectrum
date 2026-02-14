<?php

declare(strict_types=1);

/**
 * OpenAPI 3.0 / 3.1 requirement validator used by demo-app compliance checks.
 *
 * This validator combines:
 * - Schema-level validation result (from cebe/openapi)
 * - Normative semantic checks that are easy to regress in generators
 */
final class OpenApiRequirementValidator
{
    /**
     * @var array<int, string>
     */
    private const HTTP_METHODS = [
        'get',
        'put',
        'post',
        'delete',
        'options',
        'head',
        'patch',
        'trace',
    ];

    /**
     * @var array<int, string>
     */
    private const PATH_ITEM_KEYS = [
        '$ref',
        'summary',
        'description',
        'servers',
        'parameters',
        'get',
        'put',
        'post',
        'delete',
        'options',
        'head',
        'patch',
        'trace',
    ];

    /**
     * @var array<int, array{
     *   id: string,
     *   description: string,
     *   versions: array<int, string>,
     *   method: string
     * }>
     */
    private const REQUIREMENT_DEFINITIONS = [
        ['id' => 'OAS-001', 'description' => 'Document validates against OpenAPI schema', 'versions' => ['3.0', '3.1'], 'method' => 'checkSchemaValidation'],
        ['id' => 'OAS-002', 'description' => 'Root object includes required OpenAPI fields', 'versions' => ['3.0', '3.1'], 'method' => 'checkRootFields'],
        ['id' => 'OAS-003', 'description' => 'Server objects provide non-empty url', 'versions' => ['3.0', '3.1'], 'method' => 'checkServersHaveUrls'],
        ['id' => 'OAS-004', 'description' => 'Path keys follow OpenAPI path syntax', 'versions' => ['3.0', '3.1'], 'method' => 'checkPathKeys'],
        ['id' => 'OAS-005', 'description' => 'PathItem keys are valid OpenAPI fields', 'versions' => ['3.0', '3.1'], 'method' => 'checkPathItemKeys'],
        ['id' => 'OAS-006', 'description' => 'Each operation has at least one response', 'versions' => ['3.0', '3.1'], 'method' => 'checkOperationsHaveResponses'],
        ['id' => 'OAS-007', 'description' => 'OperationId values are unique', 'versions' => ['3.0', '3.1'], 'method' => 'checkOperationIdsUnique'],
        ['id' => 'OAS-008', 'description' => 'Response status keys are valid', 'versions' => ['3.0', '3.1'], 'method' => 'checkResponseStatusKeys'],
        ['id' => 'OAS-009', 'description' => 'Response objects include description', 'versions' => ['3.0', '3.1'], 'method' => 'checkResponsesHaveDescription'],
        ['id' => 'OAS-010', 'description' => 'Templated path parameters are declared and required', 'versions' => ['3.0', '3.1'], 'method' => 'checkTemplatedPathParameters'],
        ['id' => 'OAS-011', 'description' => 'Parameter objects satisfy required shape constraints', 'versions' => ['3.0', '3.1'], 'method' => 'checkParameterShape'],
        ['id' => 'OAS-012', 'description' => 'Duplicate parameter definitions are not present in same scope', 'versions' => ['3.0', '3.1'], 'method' => 'checkParameterUniqueness'],
        ['id' => 'OAS-013', 'description' => 'RequestBody content object is valid when present', 'versions' => ['3.0', '3.1'], 'method' => 'checkRequestBodyContent'],
        ['id' => 'OAS-014', 'description' => 'MediaType example/examples mutual exclusion is respected', 'versions' => ['3.0', '3.1'], 'method' => 'checkMediaTypeExamples'],
        ['id' => 'OAS-015', 'description' => 'Security requirement objects are structurally valid', 'versions' => ['3.0', '3.1'], 'method' => 'checkSecurityRequirements'],
        ['id' => 'OAS-016', 'description' => 'Security schemes satisfy required fields by scheme type', 'versions' => ['3.0', '3.1'], 'method' => 'checkSecuritySchemes'],
        ['id' => 'OAS-017', 'description' => 'Link objects satisfy operationId/operationRef rules', 'versions' => ['3.0', '3.1'], 'method' => 'checkLinks'],
        ['id' => 'OAS-018', 'description' => 'Example objects satisfy value/externalValue rules', 'versions' => ['3.0', '3.1'], 'method' => 'checkExamples'],
        ['id' => 'OAS-019', 'description' => 'Components sections use allowed keys and object values', 'versions' => ['3.0', '3.1'], 'method' => 'checkComponentsSections'],
        ['id' => 'OAS-020', 'description' => 'Schema objects satisfy semantic guard rules', 'versions' => ['3.0', '3.1'], 'method' => 'checkSchemas'],
        ['id' => 'OAS-021', 'description' => 'Callback and webhook objects use valid map structures', 'versions' => ['3.0', '3.1'], 'method' => 'checkCallbacksAndWebhooks'],
        ['id' => 'OAS-022', 'description' => 'Root tags are unique by name', 'versions' => ['3.0', '3.1'], 'method' => 'checkRootTags'],
        ['id' => 'OAS30-001', 'description' => 'OpenAPI 3.0 forbids jsonSchemaDialect and webhooks', 'versions' => ['3.0'], 'method' => 'checkOpenApi30Specific'],
        ['id' => 'OAS30-002', 'description' => 'OpenAPI 3.0 schema uses scalar type keyword', 'versions' => ['3.0'], 'method' => 'checkOpenApi30TypeKeyword'],
        ['id' => 'OAS31-001', 'description' => 'OpenAPI 3.1 requires jsonSchemaDialect and webhooks', 'versions' => ['3.1'], 'method' => 'checkOpenApi31Specific'],
        ['id' => 'OAS31-002', 'description' => 'OpenAPI 3.1 forbids nullable keyword', 'versions' => ['3.1'], 'method' => 'checkOpenApi31NullableKeyword'],
    ];

    /**
     * @return array<int, array{
     *   id: string,
     *   description: string,
     *   versions: array<int, string>
     * }>
     */
    public function requirementDefinitions(): array
    {
        return array_map(
            static fn (array $definition): array => [
                'id' => $definition['id'],
                'description' => $definition['description'],
                'versions' => $definition['versions'],
            ],
            self::REQUIREMENT_DEFINITIONS
        );
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  array<int, string>  $schemaErrors
     * @return array{
     *   summary: array{total: int, passed: int, failed: int, skipped: int},
     *   checks: array<int, array{id: string, description: string, versions: array<int, string>, status: string, failures: array<int, string>}>,
     *   failures: array<int, string>
     * }
     */
    public function validate(
        array $spec,
        string $rawJson,
        string $expectedVersion,
        bool $schemaValid,
        array $schemaErrors
    ): array {
        $majorVersion = str_starts_with($expectedVersion, '3.1.') ? '3.1' : '3.0';

        $context = [
            'spec' => $spec,
            'raw_json' => $rawJson,
            'expected_version' => $expectedVersion,
            'major_version' => $majorVersion,
            'schema_valid' => $schemaValid,
            'schema_errors' => $schemaErrors,
        ];

        $checks = [];
        $flattenedFailures = [];
        $passed = 0;
        $failed = 0;
        $skipped = 0;

        foreach (self::REQUIREMENT_DEFINITIONS as $definition) {
            if (! in_array($majorVersion, $definition['versions'], true)) {
                $checks[] = [
                    'id' => $definition['id'],
                    'description' => $definition['description'],
                    'versions' => $definition['versions'],
                    'status' => 'skip',
                    'failures' => [],
                ];
                $skipped++;

                continue;
            }

            $method = $definition['method'];
            /** @var array<int, string> $violations */
            $violations = $this->{$method}($context);

            if ($violations === []) {
                $checks[] = [
                    'id' => $definition['id'],
                    'description' => $definition['description'],
                    'versions' => $definition['versions'],
                    'status' => 'pass',
                    'failures' => [],
                ];
                $passed++;

                continue;
            }

            $checks[] = [
                'id' => $definition['id'],
                'description' => $definition['description'],
                'versions' => $definition['versions'],
                'status' => 'fail',
                'failures' => $violations,
            ];
            $failed++;

            foreach ($violations as $violation) {
                $flattenedFailures[] = '['.$definition['id'].'] '.$violation;
            }
        }

        return [
            'summary' => [
                'total' => count(self::REQUIREMENT_DEFINITIONS),
                'passed' => $passed,
                'failed' => $failed,
                'skipped' => $skipped,
            ],
            'checks' => $checks,
            'failures' => $flattenedFailures,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    private function checkSchemaValidation(array $context): array
    {
        if ($context['schema_valid'] === true) {
            return [];
        }

        $violations = ['OpenAPI schema validator returned errors'];
        foreach ($context['schema_errors'] as $schemaError) {
            $violations[] = $schemaError;
        }

        return $violations;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    private function checkRootFields(array $context): array
    {
        /** @var array<string, mixed> $spec */
        $spec = $context['spec'];
        /** @var string $expectedVersion */
        $expectedVersion = $context['expected_version'];

        $violations = [];

        $actual = $spec['openapi'] ?? null;
        if (! is_string($actual) || trim($actual) === '') {
            $violations[] = 'missing or invalid root openapi version';
        } elseif ($actual !== $expectedVersion) {
            $violations[] = "expected openapi version {$expectedVersion}, got {$actual}";
        }

        if (! isset($spec['info']) || ! is_array($spec['info'])) {
            $violations[] = 'missing info object';
        } else {
            $title = $spec['info']['title'] ?? null;
            if (! is_string($title) || trim($title) === '') {
                $violations[] = 'info.title must be a non-empty string';
            }

            $version = $spec['info']['version'] ?? null;
            if (! is_string($version) || trim($version) === '') {
                $violations[] = 'info.version must be a non-empty string';
            }
        }

        if (! isset($spec['paths']) || ! is_array($spec['paths'])) {
            $violations[] = 'missing paths object';
        }

        return $violations;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    private function checkServersHaveUrls(array $context): array
    {
        /** @var array<string, mixed> $spec */
        $spec = $context['spec'];

        if (! isset($spec['servers'])) {
            return [];
        }

        $violations = [];

        if (! is_array($spec['servers'])) {
            return ['servers must be an array'];
        }

        foreach ($spec['servers'] as $index => $server) {
            if (! is_array($server)) {
                $violations[] = "servers[{$index}] must be an object";

                continue;
            }

            $url = $server['url'] ?? null;
            if (! is_string($url) || trim($url) === '') {
                $violations[] = "servers[{$index}].url must be a non-empty string";
            }
        }

        return $violations;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    private function checkPathKeys(array $context): array
    {
        /** @var array<string, mixed> $spec */
        $spec = $context['spec'];

        if (! isset($spec['paths']) || ! is_array($spec['paths'])) {
            return ['paths must be an object'];
        }

        $violations = [];
        foreach ($spec['paths'] as $path => $pathItem) {
            if (! is_string($path) || ! str_starts_with($path, '/')) {
                $violations[] = "path key must start with '/': {$path}";
            }

            if (is_string($path) && (str_contains($path, '?') || str_contains($path, '#'))) {
                $violations[] = "path key must not contain query/hash: {$path}";
            }

            if (! is_array($pathItem)) {
                $violations[] = "path item for {$path} must be an object";
            }
        }

        return $violations;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    private function checkPathItemKeys(array $context): array
    {
        /** @var array<string, mixed> $spec */
        $spec = $context['spec'];

        $violations = [];
        foreach ($this->collectPathItemContexts($spec) as $pathContext) {
            /** @var array<string, mixed> $pathItem */
            $pathItem = $pathContext['path_item'];
            /** @var string $pointer */
            $pointer = $pathContext['pointer'];

            foreach ($pathItem as $key => $_value) {
                if (! is_string($key)) {
                    $violations[] = "{$pointer} has non-string key";

                    continue;
                }

                if (in_array($key, self::PATH_ITEM_KEYS, true) || $this->isExtensionKey($key)) {
                    continue;
                }

                $violations[] = "{$pointer} contains invalid PathItem key: {$key}";
            }
        }

        return $violations;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    private function checkOperationsHaveResponses(array $context): array
    {
        /** @var array<string, mixed> $spec */
        $spec = $context['spec'];

        $violations = [];
        foreach ($this->collectOperationContexts($spec) as $operationContext) {
            /** @var string $method */
            $method = $operationContext['method'];
            /** @var string $pointer */
            $pointer = $operationContext['pointer'];
            /** @var array<string, mixed> $operation */
            $operation = $operationContext['operation'];

            if (! isset($operation['responses']) || ! is_array($operation['responses']) || $operation['responses'] === []) {
                $violations[] = "{$pointer} ({$method}) must define at least one response";
            }
        }

        return $violations;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    private function checkOperationIdsUnique(array $context): array
    {
        /** @var array<string, mixed> $spec */
        $spec = $context['spec'];

        $seen = [];
        $violations = [];

        foreach ($this->collectOperationContexts($spec) as $operationContext) {
            /** @var array<string, mixed> $operation */
            $operation = $operationContext['operation'];
            /** @var string $pointer */
            $pointer = $operationContext['pointer'];

            $operationId = $operation['operationId'] ?? null;
            if (! is_string($operationId) || trim($operationId) === '') {
                continue;
            }

            if (isset($seen[$operationId])) {
                $violations[] = "operationId '{$operationId}' is duplicated ({$seen[$operationId]} and {$pointer})";

                continue;
            }

            $seen[$operationId] = $pointer;
        }

        return $violations;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    private function checkResponseStatusKeys(array $context): array
    {
        /** @var array<string, mixed> $spec */
        $spec = $context['spec'];

        $violations = [];
        foreach ($this->collectResponseContexts($spec) as $responseContext) {
            /** @var string $statusCode */
            $statusCode = $responseContext['status'];
            /** @var string $pointer */
            $pointer = $responseContext['pointer'];

            if (preg_match('/^(default|[1-5][0-9]{2}|[1-5]XX)$/', $statusCode) === 1) {
                continue;
            }

            $violations[] = "{$pointer} has invalid response status key '{$statusCode}'";
        }

        return $violations;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    private function checkResponsesHaveDescription(array $context): array
    {
        /** @var array<string, mixed> $spec */
        $spec = $context['spec'];

        $violations = [];
        foreach ($this->collectResponseContexts($spec) as $responseContext) {
            /** @var array<string, mixed> $response */
            $response = $responseContext['response'];
            /** @var string $pointer */
            $pointer = $responseContext['pointer'];

            $resolved = $this->resolveObject($spec, $response);
            if ($resolved === null) {
                continue;
            }

            $description = $resolved['description'] ?? null;
            if (! is_string($description) || trim($description) === '') {
                $violations[] = "{$pointer} must include non-empty description";
            }
        }

        return $violations;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    private function checkTemplatedPathParameters(array $context): array
    {
        /** @var array<string, mixed> $spec */
        $spec = $context['spec'];

        if (! isset($spec['paths']) || ! is_array($spec['paths'])) {
            return [];
        }

        $violations = [];

        foreach ($spec['paths'] as $path => $pathItem) {
            if (! is_string($path) || ! is_array($pathItem)) {
                continue;
            }

            preg_match_all('/\{([^}]+)\}/', $path, $matches);
            $expected = array_map(
                static fn (string $value): string => rtrim($value, '?'),
                $matches[1] ?? []
            );

            if ($expected === []) {
                continue;
            }

            foreach ($this->operationEntries($pathItem) as $method => $operation) {
                $declared = [];
                foreach ($this->mergePathAndOperationParameters($spec, $pathItem, $operation) as $parameter) {
                    $name = $parameter['name'] ?? null;
                    $in = $parameter['in'] ?? null;
                    if (! is_string($name) || ! is_string($in)) {
                        continue;
                    }

                    $declared[$name] = [
                        'in' => $in,
                        'required' => $parameter['required'] ?? null,
                    ];
                }

                foreach ($expected as $name) {
                    if (! isset($declared[$name])) {
                        $violations[] = "/paths/{$path}/{$method} missing path parameter '{$name}'";

                        continue;
                    }

                    if ($declared[$name]['in'] !== 'path') {
                        $violations[] = "/paths/{$path}/{$method} parameter '{$name}' must use in=path";
                    }

                    if ($declared[$name]['required'] !== true) {
                        $violations[] = "/paths/{$path}/{$method} parameter '{$name}' must set required=true";
                    }
                }
            }
        }

        return $violations;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    private function checkParameterShape(array $context): array
    {
        /** @var array<string, mixed> $spec */
        $spec = $context['spec'];

        $violations = [];

        foreach ($this->collectParameterContexts($spec) as $parameterContext) {
            /** @var string $pointer */
            $pointer = $parameterContext['pointer'];
            /** @var array<string, mixed> $parameter */
            $parameter = $parameterContext['parameter'];

            $resolved = $this->resolveObject($spec, $parameter);
            if ($resolved === null) {
                continue;
            }

            $name = $resolved['name'] ?? null;
            if (! is_string($name) || trim($name) === '') {
                $violations[] = "{$pointer} parameter.name must be non-empty string";
            }

            $in = $resolved['in'] ?? null;
            if (! is_string($in) || ! in_array($in, ['query', 'header', 'path', 'cookie'], true)) {
                $violations[] = "{$pointer} parameter.in must be query/header/path/cookie";
            }

            if ($in === 'path' && ($resolved['required'] ?? null) !== true) {
                $violations[] = "{$pointer} path parameter must set required=true";
            }

            $hasSchema = array_key_exists('schema', $resolved);
            $hasContent = array_key_exists('content', $resolved);

            if ($hasSchema === $hasContent) {
                $violations[] = "{$pointer} parameter must define exactly one of schema/content";
            }

            if ($hasContent) {
                if (! is_array($resolved['content']) || $resolved['content'] === []) {
                    $violations[] = "{$pointer} parameter.content must be non-empty object";
                } elseif (count($resolved['content']) !== 1) {
                    $violations[] = "{$pointer} parameter.content must define exactly one media type";
                }
            }

            if ($hasSchema && ! is_array($resolved['schema'])) {
                $violations[] = "{$pointer} parameter.schema must be an object";
            }

            if (array_key_exists('example', $resolved) && array_key_exists('examples', $resolved)) {
                $violations[] = "{$pointer} parameter cannot define both example and examples";
            }
        }

        return $violations;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    private function checkParameterUniqueness(array $context): array
    {
        /** @var array<string, mixed> $spec */
        $spec = $context['spec'];

        $violations = [];

        foreach ($this->collectPathItemContexts($spec) as $pathContext) {
            /** @var array<string, mixed> $pathItem */
            $pathItem = $pathContext['path_item'];
            /** @var string $pointer */
            $pointer = $pathContext['pointer'];

            if (isset($pathItem['parameters']) && is_array($pathItem['parameters'])) {
                $duplicates = $this->duplicateParameterKeys($spec, $pathItem['parameters']);
                foreach ($duplicates as $key => $count) {
                    $violations[] = "{$pointer}/parameters contains duplicate '{$key}' ({$count} times)";
                }
            }

            foreach ($this->operationEntries($pathItem) as $method => $operation) {
                if (! isset($operation['parameters']) || ! is_array($operation['parameters'])) {
                    continue;
                }

                $duplicates = $this->duplicateParameterKeys($spec, $operation['parameters']);
                foreach ($duplicates as $key => $count) {
                    $violations[] = "{$pointer}/{$method}/parameters contains duplicate '{$key}' ({$count} times)";
                }
            }
        }

        return $violations;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    private function checkRequestBodyContent(array $context): array
    {
        /** @var array<string, mixed> $spec */
        $spec = $context['spec'];

        $violations = [];

        foreach ($this->collectOperationContexts($spec) as $operationContext) {
            /** @var array<string, mixed> $operation */
            $operation = $operationContext['operation'];
            /** @var string $pointer */
            $pointer = $operationContext['pointer'];

            if (! array_key_exists('requestBody', $operation) || ! is_array($operation['requestBody'])) {
                continue;
            }

            $requestBody = $this->resolveObject($spec, $operation['requestBody']);
            if ($requestBody === null) {
                continue;
            }

            if (! isset($requestBody['content']) || ! is_array($requestBody['content']) || $requestBody['content'] === []) {
                $violations[] = "{$pointer}/requestBody must include non-empty content";

                continue;
            }

            foreach ($requestBody['content'] as $mediaType => $mediaTypeObject) {
                if (! is_array($mediaTypeObject)) {
                    $violations[] = "{$pointer}/requestBody/content/{$mediaType} must be an object";
                }
            }
        }

        return $violations;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    private function checkMediaTypeExamples(array $context): array
    {
        /** @var array<string, mixed> $spec */
        $spec = $context['spec'];

        $violations = [];
        foreach ($this->collectMediaTypeContexts($spec) as $mediaTypeContext) {
            /** @var string $pointer */
            $pointer = $mediaTypeContext['pointer'];
            /** @var array<string, mixed> $mediaType */
            $mediaType = $mediaTypeContext['media_type'];

            if (array_key_exists('example', $mediaType) && array_key_exists('examples', $mediaType)) {
                $violations[] = "{$pointer} cannot define both example and examples";
            }

            if (array_key_exists('examples', $mediaType) && ! is_array($mediaType['examples'])) {
                $violations[] = "{$pointer}/examples must be an object";
            }
        }

        return $violations;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    private function checkSecurityRequirements(array $context): array
    {
        /** @var array<string, mixed> $spec */
        $spec = $context['spec'];

        $violations = [];
        $locations = [];

        if (array_key_exists('security', $spec)) {
            $locations[] = ['pointer' => '/security', 'security' => $spec['security']];
        }

        foreach ($this->collectOperationContexts($spec) as $operationContext) {
            /** @var array<string, mixed> $operation */
            $operation = $operationContext['operation'];
            /** @var string $pointer */
            $pointer = $operationContext['pointer'];

            if (array_key_exists('security', $operation)) {
                $locations[] = ['pointer' => $pointer.'/security', 'security' => $operation['security']];
            }
        }

        foreach ($locations as $location) {
            $pointer = $location['pointer'];
            $security = $location['security'];

            if (! is_array($security)) {
                $violations[] = "{$pointer} must be an array";

                continue;
            }

            foreach ($security as $index => $requirement) {
                if (! is_array($requirement)) {
                    $violations[] = "{$pointer}/{$index} must be an object";

                    continue;
                }

                foreach ($requirement as $name => $scopes) {
                    if (! is_string($name) || $name === '') {
                        $violations[] = "{$pointer}/{$index} has invalid security scheme key";
                    }

                    if (! is_array($scopes)) {
                        $violations[] = "{$pointer}/{$index}/{$name} must be an array";

                        continue;
                    }

                    foreach ($scopes as $scopeIndex => $scope) {
                        if (! is_string($scope)) {
                            $violations[] = "{$pointer}/{$index}/{$name}/{$scopeIndex} must be a string scope";
                        }
                    }
                }
            }
        }

        return $violations;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    private function checkSecuritySchemes(array $context): array
    {
        /** @var array<string, mixed> $spec */
        $spec = $context['spec'];

        $schemes = $spec['components']['securitySchemes'] ?? null;
        if (! is_array($schemes)) {
            return [];
        }

        $violations = [];

        foreach ($schemes as $name => $scheme) {
            if (! is_array($scheme)) {
                $violations[] = "/components/securitySchemes/{$name} must be an object";

                continue;
            }

            $resolved = $this->resolveObject($spec, $scheme);
            if ($resolved === null) {
                continue;
            }

            $type = $resolved['type'] ?? null;
            if (! is_string($type) || ! in_array($type, ['apiKey', 'http', 'oauth2', 'openIdConnect'], true)) {
                $violations[] = "/components/securitySchemes/{$name} has invalid type";

                continue;
            }

            if ($type === 'http') {
                $schemeValue = $resolved['scheme'] ?? null;
                if (! is_string($schemeValue) || trim($schemeValue) === '') {
                    $violations[] = "/components/securitySchemes/{$name} (http) requires scheme";
                }
            }

            if ($type === 'apiKey') {
                $in = $resolved['in'] ?? null;
                $paramName = $resolved['name'] ?? null;

                if (! is_string($in) || ! in_array($in, ['query', 'header', 'cookie'], true)) {
                    $violations[] = "/components/securitySchemes/{$name} (apiKey) requires in=query|header|cookie";
                }

                if (! is_string($paramName) || trim($paramName) === '') {
                    $violations[] = "/components/securitySchemes/{$name} (apiKey) requires non-empty name";
                }
            }

            if ($type === 'openIdConnect') {
                $url = $resolved['openIdConnectUrl'] ?? null;
                if (! is_string($url) || trim($url) === '') {
                    $violations[] = "/components/securitySchemes/{$name} (openIdConnect) requires openIdConnectUrl";
                }
            }

            if ($type === 'oauth2') {
                $flows = $resolved['flows'] ?? null;
                if (! is_array($flows) || $flows === []) {
                    $violations[] = "/components/securitySchemes/{$name} (oauth2) requires non-empty flows";

                    continue;
                }

                foreach ($flows as $flowName => $flow) {
                    if (! is_array($flow)) {
                        $violations[] = "/components/securitySchemes/{$name}/flows/{$flowName} must be an object";

                        continue;
                    }

                    $requiresAuthUrl = in_array($flowName, ['implicit', 'authorizationCode'], true);
                    $requiresTokenUrl = in_array($flowName, ['password', 'clientCredentials', 'authorizationCode'], true);

                    if ($requiresAuthUrl) {
                        $authorizationUrl = $flow['authorizationUrl'] ?? null;
                        if (! is_string($authorizationUrl) || trim($authorizationUrl) === '') {
                            $violations[] = "/components/securitySchemes/{$name}/flows/{$flowName} requires authorizationUrl";
                        }
                    }

                    if ($requiresTokenUrl) {
                        $tokenUrl = $flow['tokenUrl'] ?? null;
                        if (! is_string($tokenUrl) || trim($tokenUrl) === '') {
                            $violations[] = "/components/securitySchemes/{$name}/flows/{$flowName} requires tokenUrl";
                        }
                    }

                    $scopes = $flow['scopes'] ?? null;
                    if (! is_array($scopes)) {
                        $violations[] = "/components/securitySchemes/{$name}/flows/{$flowName} requires scopes object";
                    }
                }
            }
        }

        return $violations;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    private function checkLinks(array $context): array
    {
        /** @var array<string, mixed> $spec */
        $spec = $context['spec'];

        $violations = [];

        foreach ($this->collectLinkContexts($spec) as $linkContext) {
            /** @var string $pointer */
            $pointer = $linkContext['pointer'];
            /** @var array<string, mixed> $link */
            $link = $linkContext['link'];

            $resolved = $this->resolveObject($spec, $link);
            if ($resolved === null) {
                continue;
            }

            $hasOperationId = array_key_exists('operationId', $resolved);
            $hasOperationRef = array_key_exists('operationRef', $resolved);

            if ($hasOperationId === $hasOperationRef) {
                $violations[] = "{$pointer} must define exactly one of operationId or operationRef";
            }

            if (array_key_exists('parameters', $resolved) && ! is_array($resolved['parameters'])) {
                $violations[] = "{$pointer}/parameters must be an object";
            }

            if (array_key_exists('server', $resolved) && ! is_array($resolved['server'])) {
                $violations[] = "{$pointer}/server must be an object";
            }
        }

        return $violations;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    private function checkExamples(array $context): array
    {
        /** @var array<string, mixed> $spec */
        $spec = $context['spec'];

        $violations = [];

        foreach ($this->collectExampleContexts($spec) as $exampleContext) {
            /** @var string $pointer */
            $pointer = $exampleContext['pointer'];
            /** @var array<string, mixed> $example */
            $example = $exampleContext['example'];

            $resolved = $this->resolveObject($spec, $example);
            if ($resolved === null) {
                continue;
            }

            if (array_key_exists('value', $resolved) && array_key_exists('externalValue', $resolved)) {
                $violations[] = "{$pointer} cannot define both value and externalValue";
            }

            if (array_key_exists('summary', $resolved) && ! is_string($resolved['summary'])) {
                $violations[] = "{$pointer}/summary must be a string";
            }

            if (array_key_exists('description', $resolved) && ! is_string($resolved['description'])) {
                $violations[] = "{$pointer}/description must be a string";
            }
        }

        return $violations;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    private function checkComponentsSections(array $context): array
    {
        /** @var array<string, mixed> $spec */
        $spec = $context['spec'];
        /** @var string $major */
        $major = $context['major_version'];

        if (! isset($spec['components'])) {
            return [];
        }

        if (! is_array($spec['components'])) {
            return ['/components must be an object'];
        }

        $allowed = [
            'schemas',
            'responses',
            'parameters',
            'examples',
            'requestBodies',
            'headers',
            'securitySchemes',
            'links',
            'callbacks',
        ];

        if ($major === '3.1') {
            $allowed[] = 'pathItems';
        }

        $violations = [];
        foreach ($spec['components'] as $key => $value) {
            if (! is_string($key)) {
                $violations[] = '/components contains non-string key';

                continue;
            }

            if (! in_array($key, $allowed, true) && ! $this->isExtensionKey($key)) {
                $violations[] = "/components/{$key} is not an allowed components section";
            }

            if (! is_array($value)) {
                $violations[] = "/components/{$key} must be an object";
            }
        }

        return $violations;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    private function checkSchemas(array $context): array
    {
        /** @var array<string, mixed> $spec */
        $spec = $context['spec'];
        /** @var string $major */
        $major = $context['major_version'];

        $violations = [];
        foreach ($this->collectSchemaContexts($spec) as $schemaContext) {
            /** @var string $pointer */
            $pointer = $schemaContext['pointer'];
            /** @var array<string, mixed> $schema */
            $schema = $schemaContext['schema'];

            $this->validateSchemaRecursive($schema, $pointer, $major, $violations);
        }

        return $violations;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    private function checkCallbacksAndWebhooks(array $context): array
    {
        /** @var array<string, mixed> $spec */
        $spec = $context['spec'];

        $violations = [];

        foreach ($this->collectOperationContexts($spec) as $operationContext) {
            /** @var array<string, mixed> $operation */
            $operation = $operationContext['operation'];
            /** @var string $pointer */
            $pointer = $operationContext['pointer'];

            if (! array_key_exists('callbacks', $operation)) {
                continue;
            }

            if (! is_array($operation['callbacks'])) {
                $violations[] = "{$pointer}/callbacks must be an object";

                continue;
            }

            foreach ($operation['callbacks'] as $callbackName => $callbackObject) {
                if (! is_array($callbackObject)) {
                    $violations[] = "{$pointer}/callbacks/{$callbackName} must be an object";

                    continue;
                }

                if ($callbackObject === []) {
                    $violations[] = "{$pointer}/callbacks/{$callbackName} must define at least one callback expression";
                }
            }
        }

        if (isset($spec['webhooks']) && ! is_array($spec['webhooks'])) {
            $violations[] = '/webhooks must be an object';
        }

        return $violations;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    private function checkRootTags(array $context): array
    {
        /** @var array<string, mixed> $spec */
        $spec = $context['spec'];
        $tags = $spec['tags'] ?? null;

        if (! is_array($tags)) {
            return [];
        }

        $violations = [];
        $seen = [];

        foreach ($tags as $index => $tag) {
            if (! is_array($tag)) {
                $violations[] = "/tags/{$index} must be an object";

                continue;
            }

            $name = $tag['name'] ?? null;
            if (! is_string($name) || trim($name) === '') {
                $violations[] = "/tags/{$index}/name must be non-empty string";

                continue;
            }

            if (isset($seen[$name])) {
                $violations[] = "tag '{$name}' is duplicated at /tags/{$index}";

                continue;
            }

            $seen[$name] = true;
        }

        return $violations;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    private function checkOpenApi30Specific(array $context): array
    {
        /** @var array<string, mixed> $spec */
        $spec = $context['spec'];

        $violations = [];

        if (array_key_exists('jsonSchemaDialect', $spec)) {
            $violations[] = 'OpenAPI 3.0.x must not include jsonSchemaDialect';
        }

        if (array_key_exists('webhooks', $spec)) {
            $violations[] = 'OpenAPI 3.0.x must not include webhooks';
        }

        return $violations;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    private function checkOpenApi30TypeKeyword(array $context): array
    {
        /** @var string $rawJson */
        $rawJson = $context['raw_json'];

        if (preg_match('/"type"\s*:\s*\[/', $rawJson) === 1) {
            return ['OpenAPI 3.0.x must not use array form of type keyword'];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    private function checkOpenApi31Specific(array $context): array
    {
        /** @var array<string, mixed> $spec */
        $spec = $context['spec'];

        $violations = [];

        $dialect = $spec['jsonSchemaDialect'] ?? null;
        if (! is_string($dialect) || trim($dialect) === '') {
            $violations[] = 'OpenAPI 3.1.x must include non-empty jsonSchemaDialect';
        }

        if (! array_key_exists('webhooks', $spec)) {
            $violations[] = 'OpenAPI 3.1.x must include webhooks key';
        } elseif (! is_array($spec['webhooks'])) {
            $violations[] = 'OpenAPI 3.1.x webhooks must be an object';
        }

        return $violations;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    private function checkOpenApi31NullableKeyword(array $context): array
    {
        /** @var string $rawJson */
        $rawJson = $context['raw_json'];

        if (preg_match('/"nullable"\s*:/', $rawJson) === 1) {
            return ['OpenAPI 3.1.x must not contain nullable keyword'];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<int, array{source: string, path: string, pointer: string, path_item: array<string, mixed>}>
     */
    private function collectPathItemContexts(array $spec): array
    {
        $contexts = [];
        $queue = [];

        if (isset($spec['paths']) && is_array($spec['paths'])) {
            foreach ($spec['paths'] as $path => $pathItem) {
                if (is_string($path) && is_array($pathItem)) {
                    $queue[] = [
                        'source' => 'paths',
                        'path' => $path,
                        'pointer' => '/paths/'.$path,
                        'path_item' => $pathItem,
                    ];
                }
            }
        }

        if (isset($spec['webhooks']) && is_array($spec['webhooks'])) {
            foreach ($spec['webhooks'] as $name => $pathItem) {
                if (is_string($name) && is_array($pathItem)) {
                    $queue[] = [
                        'source' => 'webhooks',
                        'path' => $name,
                        'pointer' => '/webhooks/'.$name,
                        'path_item' => $pathItem,
                    ];
                }
            }
        }

        if (isset($spec['components']['pathItems']) && is_array($spec['components']['pathItems'])) {
            foreach ($spec['components']['pathItems'] as $name => $pathItem) {
                if (is_string($name) && is_array($pathItem)) {
                    $queue[] = [
                        'source' => 'components.pathItems',
                        'path' => $name,
                        'pointer' => '/components/pathItems/'.$name,
                        'path_item' => $pathItem,
                    ];
                }
            }
        }

        if (isset($spec['components']['callbacks']) && is_array($spec['components']['callbacks'])) {
            foreach ($spec['components']['callbacks'] as $callbackName => $callbackObject) {
                if (! is_string($callbackName) || ! is_array($callbackObject)) {
                    continue;
                }

                foreach ($callbackObject as $expression => $pathItem) {
                    if (is_string($expression) && is_array($pathItem)) {
                        $queue[] = [
                            'source' => 'callbacks',
                            'path' => $expression,
                            'pointer' => '/components/callbacks/'.$callbackName.'/'.$expression,
                            'path_item' => $pathItem,
                        ];
                    }
                }
            }
        }

        $visited = [];
        while ($queue !== []) {
            $current = array_shift($queue);
            if (! is_array($current)) {
                continue;
            }

            $visitKey = $current['pointer'].'|'.$current['source'];
            if (isset($visited[$visitKey])) {
                continue;
            }
            $visited[$visitKey] = true;

            $contexts[] = $current;

            /** @var array<string, mixed> $pathItem */
            $pathItem = $current['path_item'];
            foreach ($this->operationEntries($pathItem) as $method => $operation) {
                if (! isset($operation['callbacks']) || ! is_array($operation['callbacks'])) {
                    continue;
                }

                foreach ($operation['callbacks'] as $callbackName => $callbackObject) {
                    if (! is_array($callbackObject)) {
                        continue;
                    }

                    foreach ($callbackObject as $expression => $callbackPathItem) {
                        if (! is_array($callbackPathItem)) {
                            continue;
                        }

                        $queue[] = [
                            'source' => 'callbacks',
                            'path' => (string) $expression,
                            'pointer' => $current['pointer'].'/'.$method.'/callbacks/'.$callbackName.'/'.$expression,
                            'path_item' => $callbackPathItem,
                        ];
                    }
                }
            }
        }

        return $contexts;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<int, array{
     *   source: string,
     *   path: string,
     *   pointer: string,
     *   method: string,
     *   operation: array<string, mixed>,
     *   path_item: array<string, mixed>
     * }>
     */
    private function collectOperationContexts(array $spec): array
    {
        $contexts = [];
        foreach ($this->collectPathItemContexts($spec) as $pathContext) {
            /** @var array<string, mixed> $pathItem */
            $pathItem = $pathContext['path_item'];
            /** @var string $pointer */
            $pointer = $pathContext['pointer'];
            /** @var string $path */
            $path = $pathContext['path'];
            /** @var string $source */
            $source = $pathContext['source'];

            foreach ($this->operationEntries($pathItem) as $method => $operation) {
                $contexts[] = [
                    'source' => $source,
                    'path' => $path,
                    'pointer' => $pointer.'/'.$method,
                    'method' => $method,
                    'operation' => $operation,
                    'path_item' => $pathItem,
                ];
            }
        }

        return $contexts;
    }

    /**
     * @param  array<string, mixed>  $pathItem
     * @return array<string, array<string, mixed>>
     */
    private function operationEntries(array $pathItem): array
    {
        $operations = [];
        foreach (self::HTTP_METHODS as $method) {
            if (isset($pathItem[$method]) && is_array($pathItem[$method])) {
                $operations[$method] = $pathItem[$method];
            }
        }

        return $operations;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<int, array{pointer: string, status: string, response: array<string, mixed>}>
     */
    private function collectResponseContexts(array $spec): array
    {
        $contexts = [];

        foreach ($this->collectOperationContexts($spec) as $operationContext) {
            /** @var array<string, mixed> $operation */
            $operation = $operationContext['operation'];
            /** @var string $pointer */
            $pointer = $operationContext['pointer'];

            if (! isset($operation['responses']) || ! is_array($operation['responses'])) {
                continue;
            }

            foreach ($operation['responses'] as $status => $response) {
                if (! is_array($response)) {
                    continue;
                }

                $contexts[] = [
                    'pointer' => $pointer.'/responses/'.$status,
                    'status' => (string) $status,
                    'response' => $response,
                ];
            }
        }

        if (isset($spec['components']['responses']) && is_array($spec['components']['responses'])) {
            foreach ($spec['components']['responses'] as $name => $response) {
                if (! is_array($response)) {
                    continue;
                }

                $contexts[] = [
                    'pointer' => '/components/responses/'.$name,
                    'status' => (string) $name,
                    'response' => $response,
                ];
            }
        }

        return $contexts;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<int, array{pointer: string, parameter: array<string, mixed>}>
     */
    private function collectParameterContexts(array $spec): array
    {
        $contexts = [];

        foreach ($this->collectPathItemContexts($spec) as $pathContext) {
            /** @var array<string, mixed> $pathItem */
            $pathItem = $pathContext['path_item'];
            /** @var string $pointer */
            $pointer = $pathContext['pointer'];

            if (isset($pathItem['parameters']) && is_array($pathItem['parameters'])) {
                foreach ($pathItem['parameters'] as $index => $parameter) {
                    if (is_array($parameter)) {
                        $contexts[] = [
                            'pointer' => $pointer.'/parameters/'.$index,
                            'parameter' => $parameter,
                        ];
                    }
                }
            }

            foreach ($this->operationEntries($pathItem) as $method => $operation) {
                if (! isset($operation['parameters']) || ! is_array($operation['parameters'])) {
                    continue;
                }

                foreach ($operation['parameters'] as $index => $parameter) {
                    if (is_array($parameter)) {
                        $contexts[] = [
                            'pointer' => $pointer.'/'.$method.'/parameters/'.$index,
                            'parameter' => $parameter,
                        ];
                    }
                }
            }
        }

        if (isset($spec['components']['parameters']) && is_array($spec['components']['parameters'])) {
            foreach ($spec['components']['parameters'] as $name => $parameter) {
                if (is_array($parameter)) {
                    $contexts[] = [
                        'pointer' => '/components/parameters/'.$name,
                        'parameter' => $parameter,
                    ];
                }
            }
        }

        return $contexts;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<int, array{pointer: string, media_type: array<string, mixed>}>
     */
    private function collectMediaTypeContexts(array $spec): array
    {
        $contexts = [];

        foreach ($this->collectOperationContexts($spec) as $operationContext) {
            /** @var array<string, mixed> $operation */
            $operation = $operationContext['operation'];
            /** @var string $pointer */
            $pointer = $operationContext['pointer'];

            if (isset($operation['requestBody']) && is_array($operation['requestBody'])) {
                $requestBody = $this->resolveObject($spec, $operation['requestBody']);
                if ($requestBody !== null && isset($requestBody['content']) && is_array($requestBody['content'])) {
                    foreach ($requestBody['content'] as $mediaType => $mediaTypeObject) {
                        if (is_array($mediaTypeObject)) {
                            $contexts[] = [
                                'pointer' => $pointer.'/requestBody/content/'.$mediaType,
                                'media_type' => $mediaTypeObject,
                            ];
                        }
                    }
                }
            }

            if (isset($operation['responses']) && is_array($operation['responses'])) {
                foreach ($operation['responses'] as $status => $response) {
                    if (! is_array($response)) {
                        continue;
                    }

                    $resolvedResponse = $this->resolveObject($spec, $response);
                    if ($resolvedResponse === null) {
                        continue;
                    }

                    if (isset($resolvedResponse['content']) && is_array($resolvedResponse['content'])) {
                        foreach ($resolvedResponse['content'] as $mediaType => $mediaTypeObject) {
                            if (is_array($mediaTypeObject)) {
                                $contexts[] = [
                                    'pointer' => $pointer.'/responses/'.$status.'/content/'.$mediaType,
                                    'media_type' => $mediaTypeObject,
                                ];
                            }
                        }
                    }
                }
            }
        }

        if (isset($spec['components']['requestBodies']) && is_array($spec['components']['requestBodies'])) {
            foreach ($spec['components']['requestBodies'] as $name => $requestBody) {
                if (! is_array($requestBody)) {
                    continue;
                }

                $resolvedBody = $this->resolveObject($spec, $requestBody);
                if ($resolvedBody === null || ! isset($resolvedBody['content']) || ! is_array($resolvedBody['content'])) {
                    continue;
                }

                foreach ($resolvedBody['content'] as $mediaType => $mediaTypeObject) {
                    if (is_array($mediaTypeObject)) {
                        $contexts[] = [
                            'pointer' => '/components/requestBodies/'.$name.'/content/'.$mediaType,
                            'media_type' => $mediaTypeObject,
                        ];
                    }
                }
            }
        }

        if (isset($spec['components']['responses']) && is_array($spec['components']['responses'])) {
            foreach ($spec['components']['responses'] as $name => $response) {
                if (! is_array($response)) {
                    continue;
                }

                $resolved = $this->resolveObject($spec, $response);
                if ($resolved === null || ! isset($resolved['content']) || ! is_array($resolved['content'])) {
                    continue;
                }

                foreach ($resolved['content'] as $mediaType => $mediaTypeObject) {
                    if (is_array($mediaTypeObject)) {
                        $contexts[] = [
                            'pointer' => '/components/responses/'.$name.'/content/'.$mediaType,
                            'media_type' => $mediaTypeObject,
                        ];
                    }
                }
            }
        }

        return $contexts;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<int, array{pointer: string, link: array<string, mixed>}>
     */
    private function collectLinkContexts(array $spec): array
    {
        $contexts = [];

        foreach ($this->collectResponseContexts($spec) as $responseContext) {
            /** @var array<string, mixed> $response */
            $response = $responseContext['response'];
            /** @var string $pointer */
            $pointer = $responseContext['pointer'];

            $resolved = $this->resolveObject($spec, $response);
            if ($resolved === null || ! isset($resolved['links']) || ! is_array($resolved['links'])) {
                continue;
            }

            foreach ($resolved['links'] as $name => $link) {
                if (is_array($link)) {
                    $contexts[] = [
                        'pointer' => $pointer.'/links/'.$name,
                        'link' => $link,
                    ];
                }
            }
        }

        if (isset($spec['components']['links']) && is_array($spec['components']['links'])) {
            foreach ($spec['components']['links'] as $name => $link) {
                if (is_array($link)) {
                    $contexts[] = [
                        'pointer' => '/components/links/'.$name,
                        'link' => $link,
                    ];
                }
            }
        }

        return $contexts;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<int, array{pointer: string, example: array<string, mixed>}>
     */
    private function collectExampleContexts(array $spec): array
    {
        $contexts = [];

        if (isset($spec['components']['examples']) && is_array($spec['components']['examples'])) {
            foreach ($spec['components']['examples'] as $name => $example) {
                if (is_array($example)) {
                    $contexts[] = [
                        'pointer' => '/components/examples/'.$name,
                        'example' => $example,
                    ];
                }
            }
        }

        foreach ($this->collectMediaTypeContexts($spec) as $mediaTypeContext) {
            /** @var string $pointer */
            $pointer = $mediaTypeContext['pointer'];
            /** @var array<string, mixed> $mediaType */
            $mediaType = $mediaTypeContext['media_type'];

            if (! isset($mediaType['examples']) || ! is_array($mediaType['examples'])) {
                continue;
            }

            foreach ($mediaType['examples'] as $name => $example) {
                if (is_array($example)) {
                    $contexts[] = [
                        'pointer' => $pointer.'/examples/'.$name,
                        'example' => $example,
                    ];
                }
            }
        }

        foreach ($this->collectParameterContexts($spec) as $parameterContext) {
            /** @var string $pointer */
            $pointer = $parameterContext['pointer'];
            /** @var array<string, mixed> $parameter */
            $parameter = $parameterContext['parameter'];

            $resolved = $this->resolveObject($spec, $parameter);
            if ($resolved === null || ! isset($resolved['examples']) || ! is_array($resolved['examples'])) {
                continue;
            }

            foreach ($resolved['examples'] as $name => $example) {
                if (is_array($example)) {
                    $contexts[] = [
                        'pointer' => $pointer.'/examples/'.$name,
                        'example' => $example,
                    ];
                }
            }
        }

        return $contexts;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<int, array{pointer: string, schema: array<string, mixed>}>
     */
    private function collectSchemaContexts(array $spec): array
    {
        $contexts = [];

        if (isset($spec['components']['schemas']) && is_array($spec['components']['schemas'])) {
            foreach ($spec['components']['schemas'] as $name => $schema) {
                if (is_array($schema)) {
                    $contexts[] = [
                        'pointer' => '/components/schemas/'.$name,
                        'schema' => $schema,
                    ];
                }
            }
        }

        foreach ($this->collectParameterContexts($spec) as $parameterContext) {
            /** @var string $pointer */
            $pointer = $parameterContext['pointer'];
            /** @var array<string, mixed> $parameter */
            $parameter = $parameterContext['parameter'];

            $resolved = $this->resolveObject($spec, $parameter);
            if ($resolved !== null && isset($resolved['schema']) && is_array($resolved['schema'])) {
                $contexts[] = [
                    'pointer' => $pointer.'/schema',
                    'schema' => $resolved['schema'],
                ];
            }
        }

        foreach ($this->collectMediaTypeContexts($spec) as $mediaTypeContext) {
            /** @var string $pointer */
            $pointer = $mediaTypeContext['pointer'];
            /** @var array<string, mixed> $mediaType */
            $mediaType = $mediaTypeContext['media_type'];

            if (isset($mediaType['schema']) && is_array($mediaType['schema'])) {
                $contexts[] = [
                    'pointer' => $pointer.'/schema',
                    'schema' => $mediaType['schema'],
                ];
            }
        }

        if (isset($spec['components']['headers']) && is_array($spec['components']['headers'])) {
            foreach ($spec['components']['headers'] as $name => $header) {
                if (! is_array($header)) {
                    continue;
                }

                $resolved = $this->resolveObject($spec, $header);
                if ($resolved === null) {
                    continue;
                }

                if (isset($resolved['schema']) && is_array($resolved['schema'])) {
                    $contexts[] = [
                        'pointer' => '/components/headers/'.$name.'/schema',
                        'schema' => $resolved['schema'],
                    ];
                }
            }
        }

        return $contexts;
    }

    /**
     * @param  array<int, mixed>  $parameters
     * @return array<string, int>
     */
    private function duplicateParameterKeys(array $spec, array $parameters): array
    {
        $counts = [];

        foreach ($parameters as $parameter) {
            if (! is_array($parameter)) {
                continue;
            }

            $resolved = $this->resolveObject($spec, $parameter);
            if ($resolved === null) {
                continue;
            }

            $name = $resolved['name'] ?? null;
            $in = $resolved['in'] ?? null;
            if (! is_string($name) || ! is_string($in)) {
                continue;
            }

            $key = $name.'|'.$in;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return array_filter($counts, static fn (int $count): bool => $count > 1);
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  array<string, mixed>  $pathItem
     * @param  array<string, mixed>  $operation
     * @return array<int, array<string, mixed>>
     */
    private function mergePathAndOperationParameters(array $spec, array $pathItem, array $operation): array
    {
        $merged = [];

        $pathParameters = $pathItem['parameters'] ?? [];
        if (is_array($pathParameters)) {
            foreach ($pathParameters as $parameter) {
                if (! is_array($parameter)) {
                    continue;
                }

                $resolved = $this->resolveObject($spec, $parameter);
                if ($resolved === null) {
                    continue;
                }

                $name = $resolved['name'] ?? null;
                $in = $resolved['in'] ?? null;
                if (! is_string($name) || ! is_string($in)) {
                    continue;
                }

                $merged[$name.'|'.$in] = $resolved;
            }
        }

        $operationParameters = $operation['parameters'] ?? [];
        if (is_array($operationParameters)) {
            foreach ($operationParameters as $parameter) {
                if (! is_array($parameter)) {
                    continue;
                }

                $resolved = $this->resolveObject($spec, $parameter);
                if ($resolved === null) {
                    continue;
                }

                $name = $resolved['name'] ?? null;
                $in = $resolved['in'] ?? null;
                if (! is_string($name) || ! is_string($in)) {
                    continue;
                }

                // operation-level parameter overrides path-level parameter
                $merged[$name.'|'.$in] = $resolved;
            }
        }

        return array_values($merged);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<int, string>  $violations
     */
    private function validateSchemaRecursive(array $schema, string $pointer, string $majorVersion, array &$violations): void
    {
        if (($schema['readOnly'] ?? false) === true && ($schema['writeOnly'] ?? false) === true) {
            $violations[] = "{$pointer} schema cannot set both readOnly and writeOnly to true";
        }

        if (array_key_exists('enum', $schema)) {
            if (! is_array($schema['enum']) || $schema['enum'] === []) {
                $violations[] = "{$pointer}/enum must be a non-empty array";
            } elseif (count(array_unique(array_map('serialize', $schema['enum']))) !== count($schema['enum'])) {
                $violations[] = "{$pointer}/enum values must be unique";
            }
        }

        foreach (['oneOf', 'anyOf', 'allOf'] as $keyword) {
            if (! array_key_exists($keyword, $schema)) {
                continue;
            }

            if (! is_array($schema[$keyword]) || $schema[$keyword] === []) {
                $violations[] = "{$pointer}/{$keyword} must be a non-empty array";

                continue;
            }

            foreach ($schema[$keyword] as $index => $childSchema) {
                if (! is_array($childSchema)) {
                    $violations[] = "{$pointer}/{$keyword}/{$index} must be an object";

                    continue;
                }

                $this->validateSchemaRecursive($childSchema, "{$pointer}/{$keyword}/{$index}", $majorVersion, $violations);
            }
        }

        if (array_key_exists('type', $schema)) {
            if (is_array($schema['type'])) {
                if ($majorVersion === '3.0') {
                    $violations[] = "{$pointer}/type must be a string in OpenAPI 3.0";
                } else {
                    if ($schema['type'] === []) {
                        $violations[] = "{$pointer}/type array must not be empty";
                    }

                    foreach ($schema['type'] as $index => $typeName) {
                        if (! is_string($typeName) || trim($typeName) === '') {
                            $violations[] = "{$pointer}/type/{$index} must be non-empty string";
                        }
                    }

                    if (count(array_unique($schema['type'])) !== count($schema['type'])) {
                        $violations[] = "{$pointer}/type array must not contain duplicates";
                    }
                }
            } elseif (! is_string($schema['type'])) {
                $violations[] = "{$pointer}/type must be string or array";
            }
        }

        if ($majorVersion === '3.1' && array_key_exists('nullable', $schema)) {
            $violations[] = "{$pointer} must not include nullable in OpenAPI 3.1";
        }

        if ($majorVersion === '3.0' && array_key_exists('nullable', $schema) && ! is_bool($schema['nullable'])) {
            $violations[] = "{$pointer}/nullable must be boolean in OpenAPI 3.0";
        }

        if (array_key_exists('required', $schema)) {
            if (! is_array($schema['required'])) {
                $violations[] = "{$pointer}/required must be an array";
            } else {
                foreach ($schema['required'] as $index => $requiredField) {
                    if (! is_string($requiredField)) {
                        $violations[] = "{$pointer}/required/{$index} must be a string";
                    }
                }
            }
        }

        if (array_key_exists('additionalProperties', $schema)
            && ! is_bool($schema['additionalProperties'])
            && ! is_array($schema['additionalProperties'])) {
            $violations[] = "{$pointer}/additionalProperties must be boolean or schema object";
        }

        if (isset($schema['properties']) && ! is_array($schema['properties'])) {
            $violations[] = "{$pointer}/properties must be an object";
        }

        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $propertyName => $propertySchema) {
                if (! is_array($propertySchema)) {
                    $violations[] = "{$pointer}/properties/{$propertyName} must be an object";

                    continue;
                }

                $this->validateSchemaRecursive($propertySchema, "{$pointer}/properties/{$propertyName}", $majorVersion, $violations);
            }
        }

        if (isset($schema['items'])) {
            if (is_array($schema['items'])) {
                $this->validateSchemaRecursive($schema['items'], "{$pointer}/items", $majorVersion, $violations);
            } elseif (! is_bool($schema['items'])) {
                $violations[] = "{$pointer}/items must be schema object".($majorVersion === '3.1' ? ' or boolean' : '');
            }
        }

        foreach (['not', 'if', 'then', 'else', 'contains', 'propertyNames'] as $keyword) {
            if (! isset($schema[$keyword])) {
                continue;
            }

            if (is_array($schema[$keyword])) {
                $this->validateSchemaRecursive($schema[$keyword], "{$pointer}/{$keyword}", $majorVersion, $violations);

                continue;
            }

            if ($majorVersion !== '3.1' || ! is_bool($schema[$keyword])) {
                $violations[] = "{$pointer}/{$keyword} must be schema object".($majorVersion === '3.1' ? ' or boolean' : '');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>|null
     */
    private function resolveObject(array $spec, array $object): ?array
    {
        $resolved = $object;

        for ($depth = 0; $depth < 8; $depth++) {
            $ref = $resolved['$ref'] ?? null;
            if (! is_string($ref)) {
                return $resolved;
            }

            $target = $this->resolveLocalRef($spec, $ref);
            if ($target === null || ! is_array($target)) {
                return null;
            }

            $resolved = $target;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>|null
     */
    private function resolveLocalRef(array $spec, string $ref): ?array
    {
        if (! str_starts_with($ref, '#/')) {
            return null;
        }

        $segments = explode('/', substr($ref, 2));
        $current = $spec;

        foreach ($segments as $segment) {
            $decoded = str_replace(['~1', '~0'], ['/', '~'], $segment);
            if (! is_array($current) || ! array_key_exists($decoded, $current)) {
                return null;
            }

            $current = $current[$decoded];
        }

        return is_array($current) ? $current : null;
    }

    private function isExtensionKey(string $key): bool
    {
        return str_starts_with($key, 'x-');
    }
}
