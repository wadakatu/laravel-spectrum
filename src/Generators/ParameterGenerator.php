<?php

namespace LaravelSpectrum\Generators;

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

        $result = [];
        foreach ($parameters as $routeParam) {
            $result[] = $routeParam;
        }

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
}
