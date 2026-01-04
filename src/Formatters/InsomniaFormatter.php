<?php

namespace LaravelSpectrum\Formatters;

use Illuminate\Support\Str;
use LaravelSpectrum\DTO\OpenApiOperation;

/**
 * Formats OpenAPI data for Insomnia collection export.
 *
 * @phpstan-import-type RouteDefinition from OpenApiOperation
 */
class InsomniaFormatter
{
    /**
     * Generate a unique ID for Insomnia resources
     */
    public function generateId(string $prefix): string
    {
        return $prefix.'_'.Str::random(24);
    }

    /**
     * Format headers for Insomnia
     */
    public function formatHeaders(array $headers): array
    {
        $formatted = [];

        foreach ($headers as $key => $value) {
            $formatted[] = [
                'name' => $key,
                'value' => $value,
            ];
        }

        return $formatted;
    }

    /**
     * Format authentication for Insomnia
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
                        'token' => '{{ _.bearer_token }}',
                        'prefix' => 'Bearer',
                    ];
                }

                if ($scheme['type'] === 'apiKey') {
                    return [
                        'type' => 'apikey',
                        'key' => '{{ _.api_key }}',
                    ];
                }

                if ($scheme['type'] === 'oauth2') {
                    return [
                        'type' => 'oauth2',
                        'grantType' => 'authorization_code',
                        'accessTokenUrl' => '{{ _.oauth2_token_url }}',
                        'authorizationUrl' => '{{ _.oauth2_auth_url }}',
                        'clientId' => '{{ _.oauth2_client_id }}',
                        'clientSecret' => '{{ _.oauth2_client_secret }}',
                        'scope' => implode(' ', $scopes),
                    ];
                }
            }
        }

        return [];
    }

    /**
     * Convert OpenAPI path to Insomnia format
     */
    public function convertPath(string $path): string
    {
        // Convert {param} to {{ _.param }}
        return preg_replace('/\{([^}]+)\}/', '{{ _.$1 }}', $path);
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
     * Generate environment data from OpenAPI servers
     */
    public function generateEnvironmentData(array $servers): array
    {
        if (empty($servers)) {
            return [
                'base_url' => '{{ _.scheme }}://{{ _.host }}{{ _.base_path }}',
                'scheme' => 'http',
                'host' => 'localhost',
                'base_path' => '/api',
            ];
        }

        $server = $servers[0];
        $url = parse_url($server['url']);

        return [
            'base_url' => '{{ _.scheme }}://{{ _.host }}{{ _.base_path }}',
            'scheme' => $url['scheme'] ?? 'http',
            'host' => $url['host'] ?? 'localhost',
            'base_path' => $url['path'] ?? '/api',
        ];
    }

    /**
     * Format query parameters for Insomnia
     */
    public function formatQueryParameters(array $parameters): array
    {
        $formatted = [];

        foreach ($parameters as $param) {
            if ($param['in'] !== 'query') {
                continue;
            }

            $formatted[] = [
                'id' => $this->generateId('pair'),
                'name' => $param['name'],
                'value' => $this->getParameterExample($param),
                'description' => $param['description'] ?? '',
                'disabled' => ! ($param['required'] ?? false),
            ];
        }

        return $formatted;
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
     * Format form data parameters
     */
    public function formatFormDataParameters(array $properties, RequestExampleFormatter $exampleFormatter): array
    {
        $params = [];

        foreach ($properties as $name => $property) {
            if ($property['type'] === 'string' && ($property['format'] ?? '') === 'binary') {
                $params[] = [
                    'id' => $this->generateId('pair'),
                    'name' => $name,
                    'value' => '',
                    'description' => $property['description'] ?? '',
                    'type' => 'file',
                    'fileName' => '',
                ];
            } else {
                $params[] = [
                    'id' => $this->generateId('pair'),
                    'name' => $name,
                    'value' => $exampleFormatter->generateExample(
                        $property['type'] ?? 'string',
                        $name
                    ),
                    'description' => $property['description'] ?? '',
                ];
            }
        }

        return $params;
    }

    /**
     * Convert authentication scheme name to Insomnia format
     */
    public function detectAuthType(string $schemeName): string
    {
        $lowerName = strtolower($schemeName);

        if (str_contains($lowerName, 'bearer')) {
            return 'bearer';
        }

        if (str_contains($lowerName, 'apikey') || str_contains($lowerName, 'api_key')) {
            return 'apikey';
        }

        if (str_contains($lowerName, 'oauth2') || str_contains($lowerName, 'oauth')) {
            return 'oauth2';
        }

        if (str_contains($lowerName, 'basic')) {
            return 'basic';
        }

        return 'none';
    }
}
