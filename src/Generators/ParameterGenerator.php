<?php

namespace LaravelSpectrum\Generators;

use LaravelSpectrum\Analyzers\InlineValidationAnalyzer;
use LaravelSpectrum\DTO\ControllerInfo;
use LaravelSpectrum\DTO\OpenApiParameter;
use LaravelSpectrum\DTO\OpenApiSchema;
use LaravelSpectrum\DTO\QueryParameterInfo;
use LaravelSpectrum\Support\QueryParameterTypeInference;

/**
 * Generates OpenAPI parameter definitions from route and controller information.
 *
 * Handles:
 * - Path parameters from route definitions
 * - Query parameters from controller analysis
 * - Enum type parameters
 * - Validation constraints (min, max, between)
 * - Validation rules as query parameters for GET requests
 */
class ParameterGenerator
{
    public function __construct(
        protected QueryParameterTypeInference $typeInference,
        protected ?InlineValidationAnalyzer $inlineValidationAnalyzer = null,
    ) {
        $this->inlineValidationAnalyzer = $inlineValidationAnalyzer ?? app(InlineValidationAnalyzer::class);
    }

    /**
     * Generate parameters for an API operation.
     *
     * @param  array{parameters: array, methods?: array<string>}  $route  Route information with parameters
     * @param  ControllerInfo  $controllerInfo  Controller analysis result
     * @param  string|null  $httpMethod  HTTP method (get, post, put, patch, delete)
     * @return array<int, OpenApiParameter> OpenAPI parameter definitions
     */
    public function generate(array $route, ControllerInfo $controllerInfo, ?string $httpMethod = null): array
    {
        // Convert route parameters to DTOs
        $parameters = array_map(
            fn (array $param) => OpenApiParameter::fromArray($param),
            $route['parameters'] ?? []
        );

        // Add enum type parameters
        $parameters = $this->addEnumParameters($parameters, $controllerInfo);

        // Add query parameters
        $parameters = $this->addQueryParameters($parameters, $controllerInfo);

        // Add validation-based query parameters for GET requests
        if ($httpMethod !== null && strtolower($httpMethod) === 'get') {
            $parameters = $this->addValidationQueryParameters($parameters, $controllerInfo);
        }

        return $parameters;
    }

    /**
     * Add enum type parameters from controller analysis.
     *
     * @param  array<int, OpenApiParameter>  $parameters  Existing parameters
     * @param  ControllerInfo  $controllerInfo  Controller analysis result
     * @return array<int, OpenApiParameter> Updated parameters
     */
    protected function addEnumParameters(array $parameters, ControllerInfo $controllerInfo): array
    {
        if (! $controllerInfo->hasEnumParameters()) {
            return $parameters;
        }

        $result = $parameters;

        foreach ($controllerInfo->enumParameters as $enumParamDTO) {
            $enumParam = $enumParamDTO->toArray();
            // Check if this is already a route parameter
            $matchIndex = null;
            foreach ($result as $index => $routeParam) {
                if ($routeParam->name === $enumParam['name']) {
                    $matchIndex = $index;
                    break;
                }
            }

            if ($matchIndex !== null) {
                // Update existing route parameter with enum schema
                $enumSchema = new OpenApiSchema(
                    type: $enumParam['type'],
                    enum: $enumParam['enum'],
                );
                $result[$matchIndex] = new OpenApiParameter(
                    name: $result[$matchIndex]->name,
                    in: $result[$matchIndex]->in,
                    required: $result[$matchIndex]->required,
                    schema: $enumSchema,
                    description: ! empty($enumParam['description']) ? $enumParam['description'] : $result[$matchIndex]->description,
                );
            } else {
                // If not a route parameter, add as query parameter
                $enumSchema = new OpenApiSchema(
                    type: $enumParam['type'],
                    enum: $enumParam['enum'],
                );
                $result[] = new OpenApiParameter(
                    name: $enumParam['name'],
                    in: OpenApiParameter::IN_QUERY,
                    required: $enumParam['required'],
                    schema: $enumSchema,
                    description: ! empty($enumParam['description']) ? $enumParam['description'] : null,
                );
            }
        }

        return $result;
    }

    /**
     * Add query parameters from controller analysis.
     *
     * @param  array<int, OpenApiParameter>  $parameters  Existing parameters
     * @param  ControllerInfo  $controllerInfo  Controller analysis result
     * @return array<int, OpenApiParameter> Updated parameters
     */
    protected function addQueryParameters(array $parameters, ControllerInfo $controllerInfo): array
    {
        if (! $controllerInfo->hasQueryParameters()) {
            return $parameters;
        }

        foreach ($controllerInfo->queryParameters as $queryParamDTO) {
            $queryParam = $queryParamDTO->toArray();
            $type = $queryParam['type'] ?? 'string';

            // Build schema
            $schema = OpenApiSchema::fromType($type);

            // Add default value
            if (isset($queryParam['default'])) {
                $schema = new OpenApiSchema(
                    type: $schema->type,
                    format: $schema->format,
                    default: $queryParam['default'],
                    enum: $queryParam['enum'] ?? null,
                    items: $type === 'array' ? OpenApiSchema::string() : null,
                );
            } elseif (isset($queryParam['enum'])) {
                $schema = $schema->withEnum($queryParam['enum']);
            }

            // Add items for array types
            if ($type === 'array' && $schema->items === null) {
                $schema = new OpenApiSchema(
                    type: 'array',
                    format: $schema->format,
                    default: $schema->default,
                    enum: $schema->enum,
                    items: OpenApiSchema::string(),
                );
            }

            // Add validation constraints
            if (isset($queryParam['validation_rules'])) {
                $constraints = $this->typeInference->getConstraintsFromRules($queryParam['validation_rules']);
                if (! empty($constraints)) {
                    $schema = new OpenApiSchema(
                        type: $schema->type,
                        format: $schema->format,
                        default: $schema->default,
                        enum: $schema->enum,
                        minimum: $constraints['minimum'] ?? $schema->minimum,
                        maximum: $constraints['maximum'] ?? $schema->maximum,
                        minLength: $constraints['minLength'] ?? $schema->minLength,
                        maxLength: $constraints['maxLength'] ?? $schema->maxLength,
                        pattern: $constraints['pattern'] ?? $schema->pattern,
                        items: $schema->items,
                    );
                }
            }

            // Create parameter DTO
            $param = new OpenApiParameter(
                name: $queryParam['name'],
                in: OpenApiParameter::IN_QUERY,
                required: $queryParam['required'] ?? false,
                schema: $schema,
                description: $queryParam['description'] ?? null,
            );

            // Apply style and explode for array types
            if ($type === 'array') {
                $includeStyle = config('spectrum.parameters.include_style', true);
                if ($includeStyle) {
                    $style = config('spectrum.parameters.array_style', 'form');
                    $explode = config('spectrum.parameters.array_explode', true);
                    $param = $param->withStyleAndExplode($style, $explode);
                }
            }

            $parameters[] = $param;
        }

        return $parameters;
    }

    /**
     * Add query parameters from inline validation rules for GET requests.
     *
     * @param  array<int, OpenApiParameter>  $parameters  Existing parameters
     * @param  ControllerInfo  $controllerInfo  Controller analysis result
     * @return array<int, OpenApiParameter> Updated parameters
     */
    protected function addValidationQueryParameters(array $parameters, ControllerInfo $controllerInfo): array
    {
        if (! $controllerInfo->hasInlineValidation()) {
            return $parameters;
        }

        $inlineValidation = $controllerInfo->inlineValidation;
        if ($inlineValidation === null || ! $inlineValidation->hasRules()) {
            return $parameters;
        }

        foreach ($inlineValidation->rules as $fieldName => $rules) {
            // Convert dot notation to bracket notation (filter.name → filter[name])
            $parameterName = $this->convertToBracketNotation($fieldName);

            // Normalize rules to array
            $rulesArray = is_string($rules) ? explode('|', $rules) : $rules;

            // Infer type from validation rules
            $type = $this->typeInference->inferFromValidationRules($rulesArray) ?? 'string';

            // Check if required
            $required = in_array('required', $rulesArray, true);

            // Build schema
            $schema = OpenApiSchema::fromType($type);

            // Add validation constraints
            $constraints = $this->typeInference->getConstraintsFromRules($rulesArray);
            if (! empty($constraints)) {
                $schema = new OpenApiSchema(
                    type: $schema->type,
                    format: $schema->format,
                    default: $schema->default,
                    enum: $schema->enum,
                    minimum: $constraints['minimum'] ?? $schema->minimum,
                    maximum: $constraints['maximum'] ?? $schema->maximum,
                    minLength: $constraints['minLength'] ?? $schema->minLength,
                    maxLength: $constraints['maxLength'] ?? $schema->maxLength,
                    pattern: $constraints['pattern'] ?? $schema->pattern,
                    items: $type === 'array' ? OpenApiSchema::string() : null,
                );
            }

            // Add items for array types
            if ($type === 'array' && $schema->items === null) {
                $schema = new OpenApiSchema(
                    type: 'array',
                    format: $schema->format,
                    default: $schema->default,
                    enum: $schema->enum,
                    items: OpenApiSchema::string(),
                );
            }

            // Create parameter DTO
            $param = new OpenApiParameter(
                name: $parameterName,
                in: OpenApiParameter::IN_QUERY,
                required: $required,
                schema: $schema,
            );

            // Apply style and explode for array types
            if ($type === 'array') {
                $includeStyle = config('spectrum.parameters.include_style', true);
                if ($includeStyle) {
                    $style = config('spectrum.parameters.array_style', 'form');
                    $explode = config('spectrum.parameters.array_explode', true);
                    $param = $param->withStyleAndExplode($style, $explode);
                }
            }

            $parameters[] = $param;
        }

        return $parameters;
    }

    /**
     * Convert dot notation field name to bracket notation.
     *
     * Examples:
     * - filter.name → filter[name]
     * - page.number → page[number]
     * - data.user.email → data[user][email]
     */
    protected function convertToBracketNotation(string $fieldName): string
    {
        if (! str_contains($fieldName, '.')) {
            return $fieldName;
        }

        $parts = explode('.', $fieldName);
        $result = array_shift($parts);

        foreach ($parts as $part) {
            $result .= '['.$part.']';
        }

        return $result;
    }

    /**
     * Convert a QueryParameterInfo to an OpenApiParameter.
     */
    public function createFromQueryParameterInfo(QueryParameterInfo $info): OpenApiParameter
    {
        // Build schema from the info
        $schema = OpenApiSchema::fromType($info->type);

        // Add default value
        if ($info->default !== null) {
            $schema = new OpenApiSchema(
                type: $schema->type,
                format: $schema->format,
                default: $info->default,
                enum: $info->enum,
                items: $info->type === 'array' ? OpenApiSchema::string() : null,
            );
        } elseif ($info->enum !== null) {
            $schema = $schema->withEnum($info->enum);
        }

        // Add items for array types
        if ($info->type === 'array' && $schema->items === null) {
            $schema = new OpenApiSchema(
                type: 'array',
                format: $schema->format,
                default: $schema->default,
                enum: $schema->enum,
                items: OpenApiSchema::string(),
            );
        }

        // Add validation constraints
        if ($info->validationRules !== null) {
            $constraints = $this->typeInference->getConstraintsFromRules($info->validationRules);
            if (! empty($constraints)) {
                $schema = new OpenApiSchema(
                    type: $schema->type,
                    format: $schema->format,
                    default: $schema->default,
                    enum: $schema->enum,
                    minimum: $constraints['minimum'] ?? $schema->minimum,
                    maximum: $constraints['maximum'] ?? $schema->maximum,
                    minLength: $constraints['minLength'] ?? $schema->minLength,
                    maxLength: $constraints['maxLength'] ?? $schema->maxLength,
                    pattern: $constraints['pattern'] ?? $schema->pattern,
                    items: $schema->items,
                );
            }
        }

        // Create the parameter
        $param = OpenApiParameter::fromQueryParameterInfo($info);

        // Apply style and explode for array types
        if ($info->type === 'array') {
            $includeStyle = config('spectrum.parameters.include_style', true);
            if ($includeStyle) {
                $style = config('spectrum.parameters.array_style', 'form');
                $explode = config('spectrum.parameters.array_explode', true);
                $param = $param->withStyleAndExplode($style, $explode);
            }
        }

        // Update schema with constraints
        return $param->withSchema($schema);
    }

    /**
     * Convert multiple QueryParameterInfo objects to OpenApiParameter objects.
     *
     * @param  array<int, QueryParameterInfo>  $infos
     * @return array<int, OpenApiParameter>
     */
    public function createMultipleFromQueryParameterInfos(array $infos): array
    {
        return array_map(
            fn (QueryParameterInfo $info) => $this->createFromQueryParameterInfo($info),
            $infos
        );
    }
}
