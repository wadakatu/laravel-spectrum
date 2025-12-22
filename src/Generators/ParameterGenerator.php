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

        foreach ($controllerInfo['enumParameters'] as $enumParam) {
            // Check if this is already a route parameter
            $isRouteParam = false;
            foreach ($parameters as &$routeParam) {
                if ($routeParam['name'] === $enumParam['name']) {
                    $isRouteParam = true;
                    // Add enum information to existing route parameter
                    $routeParam['schema'] = [
                        'type' => $enumParam['type'],
                        'enum' => $enumParam['enum'],
                    ];
                    if ($enumParam['description']) {
                        $routeParam['description'] = $enumParam['description'];
                    }
                    break;
                }
            }

            // If not a route parameter, add as query parameter
            if (! $isRouteParam) {
                $parameters[] = [
                    'name' => $enumParam['name'],
                    'in' => 'query',
                    'required' => $enumParam['required'],
                    'schema' => [
                        'type' => $enumParam['type'],
                        'enum' => $enumParam['enum'],
                    ],
                    'description' => $enumParam['description'],
                ];
            }
        }

        return $parameters;
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

    /**
     * Extract constraints from validation rules.
     *
     * @param  array<string|object>  $rules  Validation rules
     * @return array<string, int> Constraints (minimum, maximum)
     */
    public function extractConstraintsFromRules(array $rules): array
    {
        $constraints = [];

        foreach ($rules as $rule) {
            if (is_string($rule)) {
                $parts = explode(':', $rule);
                $ruleName = $parts[0];
                $parameters = isset($parts[1]) ? explode(',', $parts[1]) : [];

                switch ($ruleName) {
                    case 'min':
                        if (isset($parameters[0])) {
                            $constraints['minimum'] = (int) $parameters[0];
                        }
                        break;
                    case 'max':
                        if (isset($parameters[0])) {
                            $constraints['maximum'] = (int) $parameters[0];
                        }
                        break;
                    case 'between':
                        if (isset($parameters[0]) && isset($parameters[1])) {
                            $constraints['minimum'] = (int) $parameters[0];
                            $constraints['maximum'] = (int) $parameters[1];
                        }
                        break;
                }
            }
        }

        return $constraints;
    }
}
