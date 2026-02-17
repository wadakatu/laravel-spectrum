<?php

declare(strict_types=1);

namespace LaravelSpectrum\Diff;

final class OpenApiDiffAnalyzer
{
    /**
     * @var array<int, string>
     */
    private const HTTP_METHODS = ['get', 'post', 'put', 'patch', 'delete', 'options', 'head', 'trace'];

    /**
     * @param  array<string, mixed>  $fromSpec
     * @param  array<string, mixed>  $toSpec
     * @return array{
     *   breaking_changes: array<int, array{type: string, operation: string, message: string}>,
     *   deprecations: array<int, array{type: string, operation: string, message: string}>,
     *   additions: array<int, array{type: string, operation: string, message: string}>,
     *   summary: array{breaking: int, deprecations: int, additions: int}
     * }
     */
    public function analyze(array $fromSpec, array $toSpec): array
    {
        $result = [
            'breaking_changes' => [],
            'deprecations' => [],
            'additions' => [],
            'summary' => [
                'breaking' => 0,
                'deprecations' => 0,
                'additions' => 0,
            ],
        ];

        $fromOperations = $this->collectOperations($fromSpec);
        $toOperations = $this->collectOperations($toSpec);

        $fromKeys = array_keys($fromOperations);
        $toKeys = array_keys($toOperations);
        sort($fromKeys);
        sort($toKeys);

        $removedOperationKeys = array_values(array_diff($fromKeys, $toKeys));
        foreach ($removedOperationKeys as $operationKey) {
            $this->addFinding(
                bucket: $result['breaking_changes'],
                type: 'endpoint_removed',
                operation: $operationKey,
                message: sprintf('%s  [endpoint removed]', $operationKey)
            );
        }

        $addedOperationKeys = array_values(array_diff($toKeys, $fromKeys));
        foreach ($addedOperationKeys as $operationKey) {
            $this->addFinding(
                bucket: $result['additions'],
                type: 'endpoint_added',
                operation: $operationKey,
                message: sprintf('%s  [new endpoint]', $operationKey)
            );
        }

        $sharedKeys = array_values(array_intersect($fromKeys, $toKeys));
        sort($sharedKeys);

        foreach ($sharedKeys as $operationKey) {
            $fromOperation = $fromOperations[$operationKey];
            $toOperation = $toOperations[$operationKey];

            $this->compareAuthentication($operationKey, $fromOperation, $toOperation, $result);
            $this->compareDeprecation($operationKey, $fromOperation, $toOperation, $result);
            $this->compareDescriptions($operationKey, $fromOperation, $toOperation, $result);
            $this->compareParameters($operationKey, $fromOperation, $toOperation, $result);
            $this->compareRequestBody($operationKey, $fromOperation, $toOperation, $result);
            $this->compareResponses($operationKey, $fromOperation, $toOperation, $result);
        }

        $result['summary']['breaking'] = count($result['breaking_changes']);
        $result['summary']['deprecations'] = count($result['deprecations']);
        $result['summary']['additions'] = count($result['additions']);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<string, array{
     *   path: string,
     *   method: string,
     *   operation: array<string, mixed>,
     *   path_item: array<string, mixed>,
     *   root_security: mixed
     * }>
     */
    private function collectOperations(array $spec): array
    {
        $operations = [];
        $paths = $spec['paths'] ?? [];
        if (! is_array($paths)) {
            return $operations;
        }

        foreach ($paths as $path => $pathItem) {
            if (! is_string($path) || ! is_array($pathItem)) {
                continue;
            }

            foreach (self::HTTP_METHODS as $method) {
                $operation = $pathItem[$method] ?? null;
                if (! is_array($operation)) {
                    continue;
                }

                $methodUpper = strtoupper($method);
                $operationKey = $methodUpper.' '.$path;

                $operations[$operationKey] = [
                    'path' => $path,
                    'method' => $methodUpper,
                    'operation' => $operation,
                    'path_item' => $pathItem,
                    'root_security' => $spec['security'] ?? null,
                ];
            }
        }

        return $operations;
    }

    /**
     * @param  array{
     *   operation: array<string, mixed>,
     *   root_security: mixed
     * }  $fromOperation
     * @param  array{
     *   operation: array<string, mixed>,
     *   root_security: mixed
     * }  $toOperation
     * @param  array{
     *   breaking_changes: array<int, array{type: string, operation: string, message: string}>,
     *   deprecations: array<int, array{type: string, operation: string, message: string}>,
     *   additions: array<int, array{type: string, operation: string, message: string}>
     * }  $result
     */
    private function compareAuthentication(string $operationKey, array $fromOperation, array $toOperation, array &$result): void
    {
        $fromRequiresAuth = $this->requiresAuthentication(
            operation: $fromOperation['operation'],
            rootSecurity: $fromOperation['root_security']
        );
        $toRequiresAuth = $this->requiresAuthentication(
            operation: $toOperation['operation'],
            rootSecurity: $toOperation['root_security']
        );

        if (! $fromRequiresAuth && $toRequiresAuth) {
            $this->addFinding(
                bucket: $result['breaking_changes'],
                type: 'auth_requirement_changed',
                operation: $operationKey,
                message: sprintf('%s: authentication became required', $operationKey)
            );
        }
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    private function requiresAuthentication(array $operation, mixed $rootSecurity): bool
    {
        $security = $operation['security'] ?? $rootSecurity;

        if (! is_array($security) || $security === []) {
            return false;
        }

        foreach ($security as $requirement) {
            if (is_array($requirement) && $requirement !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{
     *   operation: array<string, mixed>
     * }  $fromOperation
     * @param  array{
     *   operation: array<string, mixed>
     * }  $toOperation
     * @param  array{
     *   breaking_changes: array<int, array{type: string, operation: string, message: string}>,
     *   deprecations: array<int, array{type: string, operation: string, message: string}>,
     *   additions: array<int, array{type: string, operation: string, message: string}>
     * }  $result
     */
    private function compareDeprecation(string $operationKey, array $fromOperation, array $toOperation, array &$result): void
    {
        $fromDeprecated = ($fromOperation['operation']['deprecated'] ?? false) === true;
        $toDeprecated = ($toOperation['operation']['deprecated'] ?? false) === true;

        if (! $fromDeprecated && $toDeprecated) {
            $this->addFinding(
                bucket: $result['deprecations'],
                type: 'endpoint_newly_deprecated',
                operation: $operationKey,
                message: sprintf('%s  [newly deprecated]', $operationKey)
            );
        }
    }

    /**
     * @param  array{
     *   operation: array<string, mixed>
     * }  $fromOperation
     * @param  array{
     *   operation: array<string, mixed>
     * }  $toOperation
     * @param  array{
     *   breaking_changes: array<int, array{type: string, operation: string, message: string}>,
     *   deprecations: array<int, array{type: string, operation: string, message: string}>,
     *   additions: array<int, array{type: string, operation: string, message: string}>
     * }  $result
     */
    private function compareDescriptions(string $operationKey, array $fromOperation, array $toOperation, array &$result): void
    {
        $fromDescription = $this->normalizeText($fromOperation['operation']['description'] ?? null);
        $toDescription = $this->normalizeText($toOperation['operation']['description'] ?? null);

        if ($fromDescription === $toDescription) {
            return;
        }

        $this->addFinding(
            bucket: $result['additions'],
            type: 'description_changed',
            operation: $operationKey,
            message: sprintf('%s: description changed', $operationKey)
        );
    }

    private function normalizeText(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return trim($value);
    }

    /**
     * @param  array{
     *   operation: array<string, mixed>,
     *   path_item: array<string, mixed>
     * }  $fromOperation
     * @param  array{
     *   operation: array<string, mixed>,
     *   path_item: array<string, mixed>
     * }  $toOperation
     * @param  array{
     *   breaking_changes: array<int, array{type: string, operation: string, message: string}>,
     *   deprecations: array<int, array{type: string, operation: string, message: string}>,
     *   additions: array<int, array{type: string, operation: string, message: string}>
     * }  $result
     */
    private function compareParameters(string $operationKey, array $fromOperation, array $toOperation, array &$result): void
    {
        $fromParameters = $this->collectParameters(
            pathItem: $fromOperation['path_item'],
            operation: $fromOperation['operation']
        );
        $toParameters = $this->collectParameters(
            pathItem: $toOperation['path_item'],
            operation: $toOperation['operation']
        );

        $fromKeys = array_keys($fromParameters);
        $toKeys = array_keys($toParameters);
        sort($fromKeys);
        sort($toKeys);

        $addedParameterKeys = array_values(array_diff($toKeys, $fromKeys));
        foreach ($addedParameterKeys as $parameterKey) {
            $parameter = $toParameters[$parameterKey];
            $label = sprintf('%s (%s)', $parameter['name'], $parameter['in']);

            if ($parameter['required']) {
                $this->addFinding(
                    bucket: $result['breaking_changes'],
                    type: 'required_parameter_added',
                    operation: $operationKey,
                    message: sprintf("%s: required parameter '%s' added", $operationKey, $label)
                );
            } else {
                $this->addFinding(
                    bucket: $result['additions'],
                    type: 'optional_parameter_added',
                    operation: $operationKey,
                    message: sprintf("%s: optional parameter '%s' added", $operationKey, $label)
                );
            }
        }

        $sharedParameterKeys = array_values(array_intersect($fromKeys, $toKeys));
        sort($sharedParameterKeys);

        foreach ($sharedParameterKeys as $parameterKey) {
            $fromParameter = $fromParameters[$parameterKey];
            $toParameter = $toParameters[$parameterKey];
            $label = sprintf('%s (%s)', $toParameter['name'], $toParameter['in']);

            if (! $fromParameter['required'] && $toParameter['required']) {
                $this->addFinding(
                    bucket: $result['breaking_changes'],
                    type: 'parameter_became_required',
                    operation: $operationKey,
                    message: sprintf("%s: parameter '%s' became required", $operationKey, $label)
                );
            }

            if ($fromParameter['type'] !== null && $toParameter['type'] !== null && $fromParameter['type'] !== $toParameter['type']) {
                $this->addFinding(
                    bucket: $result['breaking_changes'],
                    type: 'parameter_type_changed',
                    operation: $operationKey,
                    message: sprintf(
                        "%s: parameter '%s' type changed: %s -> %s",
                        $operationKey,
                        $label,
                        $fromParameter['type'],
                        $toParameter['type']
                    )
                );
            }

            $this->compareEnumValues(
                operationKey: $operationKey,
                fieldLabel: sprintf("parameter '%s'", $label),
                fromEnumValues: $fromParameter['enum_values'],
                toEnumValues: $toParameter['enum_values'],
                result: $result
            );
        }
    }

    /**
     * @param  array<string, mixed>  $pathItem
     * @param  array<string, mixed>  $operation
     * @return array<string, array{
     *   name: string,
     *   in: string,
     *   required: bool,
     *   type: string|null,
     *   enum_values: array<int, string>
     * }>
     */
    private function collectParameters(array $pathItem, array $operation): array
    {
        $parametersByKey = [];

        $sources = [
            $pathItem['parameters'] ?? null,
            $operation['parameters'] ?? null,
        ];

        foreach ($sources as $parameterList) {
            if (! is_array($parameterList)) {
                continue;
            }

            foreach ($parameterList as $parameter) {
                if (! is_array($parameter)) {
                    continue;
                }

                $normalized = $this->normalizeParameter($parameter);
                if ($normalized === null) {
                    continue;
                }

                $key = $normalized['in'].':'.$normalized['name'];
                $parametersByKey[$key] = $normalized;
            }
        }

        return $parametersByKey;
    }

    /**
     * @param  array<string, mixed>  $parameter
     * @return array{
     *   name: string,
     *   in: string,
     *   required: bool,
     *   type: string|null,
     *   enum_values: array<int, string>
     * }|null
     */
    private function normalizeParameter(array $parameter): ?array
    {
        $name = $parameter['name'] ?? null;
        $in = $parameter['in'] ?? null;
        if (! is_string($name) || trim($name) === '' || ! is_string($in) || trim($in) === '') {
            return null;
        }

        $schema = $parameter['schema'] ?? null;
        if (! is_array($schema)) {
            $schema = [];
        }

        $required = $in === 'path' || (($parameter['required'] ?? false) === true);

        return [
            'name' => $name,
            'in' => $in,
            'required' => $required,
            'type' => $this->extractType($schema),
            'enum_values' => $this->normalizeEnumValues($schema['enum'] ?? null),
        ];
    }

    /**
     * @param  array{
     *   operation: array<string, mixed>
     * }  $fromOperation
     * @param  array{
     *   operation: array<string, mixed>
     * }  $toOperation
     * @param  array{
     *   breaking_changes: array<int, array{type: string, operation: string, message: string}>,
     *   deprecations: array<int, array{type: string, operation: string, message: string}>,
     *   additions: array<int, array{type: string, operation: string, message: string}>
     * }  $result
     */
    private function compareRequestBody(string $operationKey, array $fromOperation, array $toOperation, array &$result): void
    {
        $fromRequestBody = $fromOperation['operation']['requestBody'] ?? null;
        $toRequestBody = $toOperation['operation']['requestBody'] ?? null;
        $fromRequestSchema = $this->extractBodySchema($fromRequestBody);
        $toRequestSchema = $this->extractBodySchema($toRequestBody);

        if ($fromRequestSchema === null && $toRequestSchema === null) {
            return;
        }

        if ($fromRequestSchema === null && $toRequestSchema !== null) {
            $isRequired = is_array($toRequestBody) && (($toRequestBody['required'] ?? false) === true);

            if ($isRequired) {
                $this->addFinding(
                    bucket: $result['breaking_changes'],
                    type: 'required_request_body_added',
                    operation: $operationKey,
                    message: sprintf('%s: required request body added', $operationKey)
                );
            } else {
                $this->addFinding(
                    bucket: $result['additions'],
                    type: 'optional_request_body_added',
                    operation: $operationKey,
                    message: sprintf('%s: optional request body added', $operationKey)
                );
            }

            return;
        }

        if ($fromRequestSchema !== null && $toRequestSchema !== null) {
            $this->compareSchemaNode(
                operationKey: $operationKey,
                fromSchema: $fromRequestSchema,
                toSchema: $toRequestSchema,
                contextLabel: 'request body',
                scope: 'request',
                fieldPath: '',
                result: $result
            );
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractBodySchema(mixed $requestBody): ?array
    {
        if (! is_array($requestBody)) {
            return null;
        }

        $content = $requestBody['content'] ?? null;
        if (! is_array($content)) {
            return null;
        }

        $preferredMediaTypes = ['application/json', 'application/*+json', 'multipart/form-data', 'application/x-www-form-urlencoded'];
        foreach ($preferredMediaTypes as $mediaType) {
            if (! isset($content[$mediaType]) || ! is_array($content[$mediaType])) {
                continue;
            }

            $schema = $content[$mediaType]['schema'] ?? null;
            if (is_array($schema)) {
                return $schema;
            }
        }

        foreach ($content as $mediaTypeObject) {
            if (! is_array($mediaTypeObject)) {
                continue;
            }

            $schema = $mediaTypeObject['schema'] ?? null;
            if (is_array($schema)) {
                return $schema;
            }
        }

        return null;
    }

    /**
     * @param  array{
     *   operation: array<string, mixed>
     * }  $fromOperation
     * @param  array{
     *   operation: array<string, mixed>
     * }  $toOperation
     * @param  array{
     *   breaking_changes: array<int, array{type: string, operation: string, message: string}>,
     *   deprecations: array<int, array{type: string, operation: string, message: string}>,
     *   additions: array<int, array{type: string, operation: string, message: string}>
     * }  $result
     */
    private function compareResponses(string $operationKey, array $fromOperation, array $toOperation, array &$result): void
    {
        $fromResponses = $this->collectResponses($fromOperation['operation']);
        $toResponses = $this->collectResponses($toOperation['operation']);

        $fromStatusCodes = array_keys($fromResponses);
        $toStatusCodes = array_keys($toResponses);
        sort($fromStatusCodes);
        sort($toStatusCodes);

        $removedStatusCodes = array_values(array_diff($fromStatusCodes, $toStatusCodes));
        foreach ($removedStatusCodes as $statusCode) {
            $this->addFinding(
                bucket: $result['breaking_changes'],
                type: 'response_removed',
                operation: $operationKey,
                message: sprintf('%s: response %s removed', $operationKey, $statusCode)
            );
        }

        $addedStatusCodes = array_values(array_diff($toStatusCodes, $fromStatusCodes));
        foreach ($addedStatusCodes as $statusCode) {
            if ($this->isErrorStatusCode($statusCode)) {
                $this->addFinding(
                    bucket: $result['additions'],
                    type: 'error_response_added',
                    operation: $operationKey,
                    message: sprintf('%s: new error response %s added', $operationKey, $statusCode)
                );
            } else {
                $this->addFinding(
                    bucket: $result['additions'],
                    type: 'response_added',
                    operation: $operationKey,
                    message: sprintf('%s: response %s added', $operationKey, $statusCode)
                );
            }
        }

        $sharedStatusCodes = array_values(array_intersect($fromStatusCodes, $toStatusCodes));
        sort($sharedStatusCodes);

        foreach ($sharedStatusCodes as $statusCode) {
            $fromSchema = $fromResponses[$statusCode];
            $toSchema = $toResponses[$statusCode];

            if ($fromSchema === null || $toSchema === null) {
                continue;
            }

            $this->compareSchemaNode(
                operationKey: $operationKey,
                fromSchema: $fromSchema,
                toSchema: $toSchema,
                contextLabel: 'response '.$statusCode,
                scope: 'response',
                fieldPath: '',
                result: $result
            );
        }
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, array<string, mixed>|null>
     */
    private function collectResponses(array $operation): array
    {
        $responses = $operation['responses'] ?? null;
        if (! is_array($responses)) {
            return [];
        }

        $normalized = [];
        foreach ($responses as $statusCode => $response) {
            $statusCodeKey = (string) $statusCode;
            $normalized[$statusCodeKey] = $this->extractResponseSchema($response);
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractResponseSchema(mixed $response): ?array
    {
        if (! is_array($response)) {
            return null;
        }

        $content = $response['content'] ?? null;
        if (! is_array($content)) {
            return null;
        }

        if (isset($content['application/json']) && is_array($content['application/json'])) {
            $schema = $content['application/json']['schema'] ?? null;
            if (is_array($schema)) {
                return $schema;
            }
        }

        foreach ($content as $mediaTypeObject) {
            if (! is_array($mediaTypeObject)) {
                continue;
            }

            $schema = $mediaTypeObject['schema'] ?? null;
            if (is_array($schema)) {
                return $schema;
            }
        }

        return null;
    }

    private function isErrorStatusCode(string $statusCode): bool
    {
        if ($statusCode === 'default') {
            return true;
        }

        if (! ctype_digit($statusCode)) {
            return false;
        }

        $status = (int) $statusCode;

        return $status >= 400;
    }

    /**
     * @param  array<string, mixed>  $fromSchema
     * @param  array<string, mixed>  $toSchema
     * @param  array{
     *   breaking_changes: array<int, array{type: string, operation: string, message: string}>,
     *   deprecations: array<int, array{type: string, operation: string, message: string}>,
     *   additions: array<int, array{type: string, operation: string, message: string}>
     * }  $result
     */
    private function compareSchemaNode(
        string $operationKey,
        array $fromSchema,
        array $toSchema,
        string $contextLabel,
        string $scope,
        string $fieldPath,
        array &$result
    ): void {
        $fromType = $this->extractType($fromSchema);
        $toType = $this->extractType($toSchema);

        if ($fromType !== null && $toType !== null && $fromType !== $toType) {
            $this->addFinding(
                bucket: $result['breaking_changes'],
                type: 'field_type_changed',
                operation: $operationKey,
                message: sprintf(
                    "%s: %s field '%s' type changed: %s -> %s",
                    $operationKey,
                    $contextLabel,
                    $this->displayFieldPath($fieldPath),
                    $fromType,
                    $toType
                )
            );

            return;
        }

        $this->compareEnumValues(
            operationKey: $operationKey,
            fieldLabel: sprintf("%s field '%s'", $contextLabel, $this->displayFieldPath($fieldPath)),
            fromEnumValues: $this->normalizeEnumValues($fromSchema['enum'] ?? null),
            toEnumValues: $this->normalizeEnumValues($toSchema['enum'] ?? null),
            result: $result
        );

        $fromProperties = $this->extractProperties($fromSchema);
        $toProperties = $this->extractProperties($toSchema);

        if ($fromProperties !== [] || $toProperties !== []) {
            $this->compareObjectProperties(
                operationKey: $operationKey,
                fromProperties: $fromProperties,
                toProperties: $toProperties,
                fromRequired: $this->extractRequiredFields($fromSchema),
                toRequired: $this->extractRequiredFields($toSchema),
                contextLabel: $contextLabel,
                scope: $scope,
                parentPath: $fieldPath,
                result: $result
            );
        }

        $fromItems = $this->extractItems($fromSchema);
        $toItems = $this->extractItems($toSchema);

        if ($fromItems !== null && $toItems !== null) {
            $nextPath = $fieldPath === '' ? '[]' : $fieldPath.'[]';

            $this->compareSchemaNode(
                operationKey: $operationKey,
                fromSchema: $fromItems,
                toSchema: $toItems,
                contextLabel: $contextLabel,
                scope: $scope,
                fieldPath: $nextPath,
                result: $result
            );
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $fromProperties
     * @param  array<string, array<string, mixed>>  $toProperties
     * @param  array<int, string>  $fromRequired
     * @param  array<int, string>  $toRequired
     * @param  array{
     *   breaking_changes: array<int, array{type: string, operation: string, message: string}>,
     *   deprecations: array<int, array{type: string, operation: string, message: string}>,
     *   additions: array<int, array{type: string, operation: string, message: string}>
     * }  $result
     */
    private function compareObjectProperties(
        string $operationKey,
        array $fromProperties,
        array $toProperties,
        array $fromRequired,
        array $toRequired,
        string $contextLabel,
        string $scope,
        string $parentPath,
        array &$result
    ): void {
        $fromKeys = array_keys($fromProperties);
        $toKeys = array_keys($toProperties);
        sort($fromKeys);
        sort($toKeys);

        $removedKeys = array_values(array_diff($fromKeys, $toKeys));
        foreach ($removedKeys as $propertyName) {
            $propertyPath = $this->concatFieldPath($parentPath, $propertyName);

            if ($scope === 'response') {
                $this->addFinding(
                    bucket: $result['breaking_changes'],
                    type: 'response_field_removed',
                    operation: $operationKey,
                    message: sprintf(
                        "%s: %s field '%s' removed",
                        $operationKey,
                        $contextLabel,
                        $this->displayFieldPath($propertyPath)
                    )
                );
            }
        }

        $addedKeys = array_values(array_diff($toKeys, $fromKeys));
        foreach ($addedKeys as $propertyName) {
            $propertyPath = $this->concatFieldPath($parentPath, $propertyName);
            $isRequired = in_array($propertyName, $toRequired, true);

            if ($scope === 'response') {
                $this->addFinding(
                    bucket: $result['additions'],
                    type: 'response_field_added',
                    operation: $operationKey,
                    message: sprintf(
                        "%s: %s field '%s' added",
                        $operationKey,
                        $contextLabel,
                        $this->displayFieldPath($propertyPath)
                    )
                );

                continue;
            }

            if ($scope === 'request') {
                if ($isRequired) {
                    $this->addFinding(
                        bucket: $result['breaking_changes'],
                        type: 'required_request_field_added',
                        operation: $operationKey,
                        message: sprintf(
                            "%s: required %s field '%s' added",
                            $operationKey,
                            $contextLabel,
                            $this->displayFieldPath($propertyPath)
                        )
                    );
                } else {
                    $this->addFinding(
                        bucket: $result['additions'],
                        type: 'optional_request_field_added',
                        operation: $operationKey,
                        message: sprintf(
                            "%s: optional %s field '%s' added",
                            $operationKey,
                            $contextLabel,
                            $this->displayFieldPath($propertyPath)
                        )
                    );
                }
            }
        }

        $sharedKeys = array_values(array_intersect($fromKeys, $toKeys));
        sort($sharedKeys);

        foreach ($sharedKeys as $propertyName) {
            if ($scope === 'request' && ! in_array($propertyName, $fromRequired, true) && in_array($propertyName, $toRequired, true)) {
                $propertyPath = $this->concatFieldPath($parentPath, $propertyName);

                $this->addFinding(
                    bucket: $result['breaking_changes'],
                    type: 'request_field_became_required',
                    operation: $operationKey,
                    message: sprintf(
                        "%s: %s field '%s' became required",
                        $operationKey,
                        $contextLabel,
                        $this->displayFieldPath($propertyPath)
                    )
                );
            }

            $nextPath = $this->concatFieldPath($parentPath, $propertyName);

            $this->compareSchemaNode(
                operationKey: $operationKey,
                fromSchema: $fromProperties[$propertyName],
                toSchema: $toProperties[$propertyName],
                contextLabel: $contextLabel,
                scope: $scope,
                fieldPath: $nextPath,
                result: $result
            );
        }
    }

    private function concatFieldPath(string $parentPath, string $propertyName): string
    {
        if ($parentPath === '') {
            return $propertyName;
        }

        return $parentPath.'.'.$propertyName;
    }

    private function displayFieldPath(string $fieldPath): string
    {
        if ($fieldPath === '') {
            return '(root)';
        }

        return $fieldPath;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, array<string, mixed>>
     */
    private function extractProperties(array $schema): array
    {
        $properties = $schema['properties'] ?? null;
        if (! is_array($properties)) {
            return [];
        }

        $normalized = [];
        foreach ($properties as $propertyName => $propertySchema) {
            if (! is_string($propertyName) || ! is_array($propertySchema)) {
                continue;
            }

            $normalized[$propertyName] = $propertySchema;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<int, string>
     */
    private function extractRequiredFields(array $schema): array
    {
        $required = $schema['required'] ?? null;
        if (! is_array($required)) {
            return [];
        }

        $normalized = [];
        foreach ($required as $propertyName) {
            if (is_string($propertyName) && trim($propertyName) !== '') {
                $normalized[] = $propertyName;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>|null
     */
    private function extractItems(array $schema): ?array
    {
        $items = $schema['items'] ?? null;

        return is_array($items) ? $items : null;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function extractType(array $schema): ?string
    {
        $type = $schema['type'] ?? null;
        if (! is_string($type) || trim($type) === '') {
            return null;
        }

        return trim($type);
    }

    /**
     * @return array<int, string>
     */
    private function normalizeEnumValues(mixed $enumValues): array
    {
        if (! is_array($enumValues)) {
            return [];
        }

        $normalized = [];
        foreach ($enumValues as $enumValue) {
            if (is_string($enumValue) || is_int($enumValue) || is_float($enumValue) || is_bool($enumValue)) {
                $normalized[] = (string) $enumValue;
            }
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return $normalized;
    }

    /**
     * @param  array<int, string>  $fromEnumValues
     * @param  array<int, string>  $toEnumValues
     * @param  array{
     *   breaking_changes: array<int, array{type: string, operation: string, message: string}>,
     *   deprecations: array<int, array{type: string, operation: string, message: string}>,
     *   additions: array<int, array{type: string, operation: string, message: string}>
     * }  $result
     */
    private function compareEnumValues(
        string $operationKey,
        string $fieldLabel,
        array $fromEnumValues,
        array $toEnumValues,
        array &$result
    ): void {
        if ($fromEnumValues === [] && $toEnumValues === []) {
            return;
        }

        $removedValues = array_values(array_diff($fromEnumValues, $toEnumValues));
        foreach ($removedValues as $removedValue) {
            $this->addFinding(
                bucket: $result['breaking_changes'],
                type: 'enum_value_removed',
                operation: $operationKey,
                message: sprintf("%s: %s enum value '%s' removed", $operationKey, $fieldLabel, $removedValue)
            );
        }

        $addedValues = array_values(array_diff($toEnumValues, $fromEnumValues));
        foreach ($addedValues as $addedValue) {
            $this->addFinding(
                bucket: $result['additions'],
                type: 'enum_value_added',
                operation: $operationKey,
                message: sprintf("%s: %s enum value '%s' added", $operationKey, $fieldLabel, $addedValue)
            );
        }
    }

    /**
     * @param  array<int, array{type: string, operation: string, message: string}>  $bucket
     */
    private function addFinding(array &$bucket, string $type, string $operation, string $message): void
    {
        $bucket[] = [
            'type' => $type,
            'operation' => $operation,
            'message' => $message,
        ];
    }
}
