<?php

namespace LaravelSpectrum\Generators;

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
 */
class ParameterGenerator
{
    public function __construct(
        protected QueryParameterTypeInference $typeInference
    ) {}

    /**
     * Generate parameters for an API operation.
     *
     * @param  array{parameters: array}  $route  Route information with parameters
     * @param  array{enumParameters?: array, queryParameters?: array}  $controllerInfo  Controller analysis result
     * @return array<array> OpenAPI parameter definitions
     */
    public function generate(array $route, array $controllerInfo): array
    {
        $parameters = $route['parameters'] ?? [];

        // Add enum type parameters
        $parameters = $this->addEnumParameters($parameters, $controllerInfo);

        // Add query parameters
        $parameters = $this->addQueryParameters($parameters, $controllerInfo);

        return $parameters;
    }

    /**
     * Add enum type parameters from controller analysis.
     *
     * @param  array  $parameters  Existing parameters
     * @param  array  $controllerInfo  Controller analysis result
     * @return array Updated parameters
     */
    protected function addEnumParameters(array $parameters, array $controllerInfo): array
    {
        if (empty($controllerInfo['enumParameters'])) {
            return $parameters;
        }

        $result = $parameters;

        foreach ($controllerInfo['enumParameters'] as $enumParam) {
            // Check if this is already a route parameter
            $matchIndex = null;
            foreach ($result as $index => $routeParam) {
                if ($routeParam['name'] === $enumParam['name']) {
                    $matchIndex = $index;
                    break;
                }
            }

            if ($matchIndex !== null) {
                // Add enum information to existing route parameter
                $result[$matchIndex]['schema'] = [
                    'type' => $enumParam['type'],
                    'enum' => $enumParam['enum'],
                ];
                if (! empty($enumParam['description'])) {
                    $result[$matchIndex]['description'] = $enumParam['description'];
                }
            } else {
                // If not a route parameter, add as query parameter
                $newParam = [
                    'name' => $enumParam['name'],
                    'in' => 'query',
                    'required' => $enumParam['required'],
                    'schema' => [
                        'type' => $enumParam['type'],
                        'enum' => $enumParam['enum'],
                    ],
                ];
                if (! empty($enumParam['description'])) {
                    $newParam['description'] = $enumParam['description'];
                }
                $result[] = $newParam;
            }
        }

        return $result;
    }

    /**
     * Add query parameters from controller analysis.
     *
     * @param  array  $parameters  Existing parameters
     * @param  array  $controllerInfo  Controller analysis result
     * @return array Updated parameters
     */
    protected function addQueryParameters(array $parameters, array $controllerInfo): array
    {
        if (empty($controllerInfo['queryParameters'])) {
            return $parameters;
        }

        foreach ($controllerInfo['queryParameters'] as $queryParam) {
            $parameter = [
                'name' => $queryParam['name'],
                'in' => 'query',
                'required' => $queryParam['required'] ?? false,
                'schema' => [
                    'type' => $queryParam['type'],
                ],
            ];

            // Add style and explode for array types
            $parameter = $this->applyStyleAndExplode($parameter, $queryParam);

            // Add default value
            if (isset($queryParam['default'])) {
                $parameter['schema']['default'] = $queryParam['default'];
            }

            // Add enum values
            if (isset($queryParam['enum'])) {
                $parameter['schema']['enum'] = $queryParam['enum'];
            }

            // Add description
            if (isset($queryParam['description'])) {
                $parameter['description'] = $queryParam['description'];
            }

            // Add validation constraints
            if (isset($queryParam['validation_rules'])) {
                $constraints = $this->typeInference->getConstraintsFromRules($queryParam['validation_rules']);
                foreach ($constraints as $key => $value) {
                    $parameter['schema'][$key] = $value;
                }
            }

            $parameters[] = $parameter;
        }

        return $parameters;
    }

    /**
     * Apply style and explode properties for array/object type parameters.
     *
     * @param  array<string, mixed>  $parameter  The parameter definition
     * @param  array<string, mixed>  $queryParam  The query parameter info
     * @return array<string, mixed> Updated parameter with style/explode if applicable
     */
    protected function applyStyleAndExplode(array $parameter, array $queryParam): array
    {
        $type = $queryParam['type'] ?? 'string';

        // Only apply to array types
        if ($type !== 'array') {
            return $parameter;
        }

        // Add items schema for array types
        $parameter['schema']['items'] = ['type' => 'string'];

        // Check if style/explode should be included based on config
        $includeStyle = config('spectrum.parameters.include_style', true);
        if (! $includeStyle) {
            return $parameter;
        }

        // Apply style and explode from config
        $parameter['style'] = config('spectrum.parameters.array_style', 'form');
        $parameter['explode'] = config('spectrum.parameters.array_explode', true);

        return $parameter;
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
