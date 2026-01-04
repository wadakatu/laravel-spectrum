<?php

namespace LaravelSpectrum\Formatters;

use LaravelSpectrum\DTO\OpenApiOperation;

/**
 * Formats OpenAPI data for Postman collection export.
 *
 * @phpstan-import-type RouteDefinition from OpenApiOperation
 */
class PostmanFormatter
{
    /**
     * Format headers for Postman
     */
    public function formatHeaders(array $headers): array
    {
        $formatted = [];

        foreach ($headers as $key => $value) {
            $formatted[] = [
                'key' => $key,
                'value' => $value,
                'type' => 'text',
            ];
        }

        return $formatted;
    }

    /**
     * Format authentication for Postman
     */
    public function formatAuth(array $security, array $securitySchemes): array
    {
        foreach ($security as $securityRequirement) {
            foreach ($securityRequirement as $schemeName => $scopes) {
                if (! isset($securitySchemes[$schemeName])) {
                    continue;
                }

                $scheme = $securitySchemes[$schemeName];

                if ($scheme['type'] === 'http' && $scheme['scheme'] === 'bearer') {
                    return [
                        'type' => 'bearer',
                        'bearer' => [
                            [
                                'key' => 'token',
                                'value' => '{{bearer_token}}',
                                'type' => 'string',
                            ],
                        ],
                    ];
                }

                if ($scheme['type'] === 'apiKey') {
                    $location = $scheme['in'] ?? 'header';
                    $name = $scheme['name'] ?? 'X-API-Key';

                    if ($location === 'header') {
                        return [
                            'type' => 'apikey',
                            'apikey' => [
                                [
                                    'key' => 'key',
                                    'value' => $name,
                                    'type' => 'string',
                                ],
                                [
                                    'key' => 'value',
                                    'value' => '{{api_key}}',
                                    'type' => 'string',
                                ],
                                [
                                    'key' => 'in',
                                    'value' => 'header',
                                    'type' => 'string',
                                ],
                            ],
                        ];
                    }
                }

                if ($scheme['type'] === 'oauth2') {
                    return [
                        'type' => 'oauth2',
                        'oauth2' => [
                            [
                                'key' => 'accessToken',
                                'value' => '{{oauth2_access_token}}',
                                'type' => 'string',
                            ],
                        ],
                    ];
                }
            }
        }

        return [];
    }

    /**
     * Format query parameters for Postman
     */
    public function formatQueryParameters(array $parameters): array
    {
        $formatted = [];

        foreach ($parameters as $param) {
            if ($param['in'] !== 'query') {
                continue;
            }

            $formatted[] = [
                'key' => $param['name'],
                'value' => $this->getParameterExample($param),
                'description' => $param['description'] ?? '',
                'disabled' => ! ($param['required'] ?? false),
            ];
        }

        return $formatted;
    }

    /**
     * Format path parameters for Postman
     */
    public function formatPathParameters(array $parameters): array
    {
        $formatted = [];

        foreach ($parameters as $param) {
            if ($param['in'] !== 'path') {
                continue;
            }

            $formatted[] = [
                'key' => $param['name'],
                'value' => $this->getParameterExample($param),
                'description' => $param['description'] ?? '',
            ];
        }

        return $formatted;
    }

    /**
     * Convert OpenAPI path to Postman format
     */
    public function convertPath(string $path): string
    {
        // Convert {param} to :param
        return preg_replace('/\{([^}]+)\}/', ':$1', $path);
    }

    /**
     * Generate pre-request script
     */
    public function generatePreRequestScript(array $operation): array
    {
        $scripts = [];

        // Add timestamp variable if needed
        if ($this->needsTimestamp($operation)) {
            $scripts[] = "pm.variables.set('timestamp', new Date().toISOString());";
        }

        // Add request ID
        $scripts[] = 'pm.variables.set(\'request_id\', pm.variables.replaceIn(\'{{$guid}}\'));';

        return $scripts;
    }

    /**
     * Group routes by tag.
     *
     * @param  array<string, array<string, mixed>>  $paths
     * @return array<string, array<int, RouteDefinition>>
     */
    public function groupRoutesByTag(array $paths): array
    {
        $grouped = [];

        foreach ($paths as $path => $methods) {
            foreach ($methods as $method => $operation) {
                if (! in_array($method, ['get', 'post', 'put', 'patch', 'delete', 'head', 'options'])) {
                    continue;
                }

                $tags = $operation['tags'] ?? ['Default'];
                $tag = $tags[0] ?? 'Default';

                if (! isset($grouped[$tag])) {
                    $grouped[$tag] = [];
                }

                $grouped[$tag][] = [
                    'path' => $path,
                    'method' => $method,
                    'operation' => $operation,
                ];
            }
        }

        return $grouped;
    }

    /**
     * Extract content type from request body
     */
    public function getContentType(array $requestBody): string
    {
        $content = $requestBody['content'] ?? [];

        if (isset($content['application/json'])) {
            return 'application/json';
        }

        if (isset($content['multipart/form-data'])) {
            return 'multipart/form-data';
        }

        if (isset($content['application/x-www-form-urlencoded'])) {
            return 'application/x-www-form-urlencoded';
        }

        if (isset($content['text/plain'])) {
            return 'text/plain';
        }

        return 'application/json';
    }

    /**
     * Get example value for a parameter
     */
    private function getParameterExample(array $param): string
    {
        if (isset($param['example'])) {
            return (string) $param['example'];
        }

        if (isset($param['schema']['example'])) {
            return (string) $param['schema']['example'];
        }

        if (isset($param['schema']['default'])) {
            $default = $param['schema']['default'];

            return is_array($default) ? json_encode($default) : (string) $default;
        }

        if (isset($param['schema']['enum']) && ! empty($param['schema']['enum'])) {
            return (string) $param['schema']['enum'][0];
        }

        // Generate based on type
        $type = $param['schema']['type'] ?? 'string';

        return match ($type) {
            'integer' => '1',
            'number' => '1.0',
            'boolean' => 'true',
            'array' => '[]',
            default => 'example',
        };
    }

    /**
     * Check if operation needs timestamp variable
     */
    private function needsTimestamp(array $operation): bool
    {
        // Check if any parameter or request body property might need a timestamp
        if (isset($operation['parameters'])) {
            foreach ($operation['parameters'] as $param) {
                if (str_contains(strtolower($param['name']), 'timestamp') ||
                    str_contains(strtolower($param['name']), 'date')) {
                    return true;
                }
            }
        }

        return false;
    }
}
