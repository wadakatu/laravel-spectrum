<?php

namespace LaravelSpectrum\Exporters;

use Illuminate\Support\Str;
use LaravelSpectrum\DTO\OpenApiOperation;
use LaravelSpectrum\DTO\OpenApiSpec;
use LaravelSpectrum\Formatters\PostmanFormatter;
use LaravelSpectrum\Formatters\RequestExampleFormatter;

/**
 * @phpstan-import-type OpenApiOperationType from OpenApiOperation
 * @phpstan-import-type RouteDefinition from OpenApiOperation
 */
class PostmanExporter implements ExportFormatInterface
{
    private PostmanFormatter $formatter;

    private RequestExampleFormatter $exampleFormatter;

    public function __construct(
        PostmanFormatter $formatter,
        RequestExampleFormatter $exampleFormatter
    ) {
        $this->formatter = $formatter;
        $this->exampleFormatter = $exampleFormatter;
    }

    /**
     * Export OpenAPI specification to Postman collection format.
     *
     * @param  OpenApiSpec|array<string, mixed>  $openapi  OpenAPI specification
     * @param  array<string, mixed>  $options  Export options
     * @return array<string, mixed> Postman collection
     */
    public function export(OpenApiSpec|array $openapi, array $options = []): array
    {
        $spec = $openapi instanceof OpenApiSpec ? $openapi->toArray() : $openapi;

        $collection = [
            'info' => [
                '_postman_id' => Str::uuid()->toString(),
                'name' => $spec['info']['title'] ?? 'API Collection',
                'description' => $spec['info']['description'] ?? '',
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
                '_exporter_id' => 'laravel-spectrum',
            ],
            'item' => [],
            'auth' => $this->extractAuth($spec),
            'event' => $this->generatePreRequestScripts($spec),
            'variable' => $this->generateVariables($spec),
        ];

        // Group routes by tag
        $items = $this->groupRoutesByTag($spec);

        foreach ($items as $tag => $routes) {
            $folder = [
                'name' => $tag,
                'item' => [],
            ];

            foreach ($routes as $route) {
                $folder['item'][] = $this->convertRoute($route, $spec);
            }

            $collection['item'][] = $folder;
        }

        return $collection;
    }

    /**
     * Convert a route to Postman item format.
     *
     * @param  RouteDefinition  $route  Route definition
     * @param  array<string, mixed>  $openapi  OpenAPI specification
     * @return array<string, mixed> Postman item
     */
    private function convertRoute(array $route, array $openapi): array
    {
        $path = $route['path'];
        $method = strtoupper($route['method']);
        $operation = $route['operation'];

        $item = [
            'name' => $operation['summary'] ?? "{$method} {$path}",
            'request' => [
                'method' => $method,
                'header' => $this->generateHeaders($operation),
                'url' => $this->generateUrl($path, $operation, $openapi),
                'description' => $operation['description'] ?? '',
            ],
            'response' => [],
        ];

        // Request body processing
        if (isset($operation['requestBody'])) {
            $body = $this->generateRequestBody($operation['requestBody']);
            if ($body) {
                $item['request']['body'] = $body;
            }
        }

        // Authentication processing
        if (isset($operation['security'])) {
            $auth = $this->generateAuth($operation['security'], $openapi);
            if (! empty($auth)) {
                $item['request']['auth'] = $auth;
            }
        }

        // Response examples
        if (isset($operation['responses'])) {
            $item['response'] = $this->generateResponseExamples($operation['responses']);
        }

        // Pre-request Script
        $preRequestScript = $this->generatePreRequestScript($operation);
        if (! empty($preRequestScript)) {
            $item['event'] = [
                [
                    'listen' => 'prerequest',
                    'script' => [
                        'exec' => $preRequestScript,
                        'type' => 'text/javascript',
                    ],
                ],
            ];
        }

        // Tests
        $tests = $this->generateTests($operation);
        if (! empty($tests)) {
            $item['event'] = $item['event'] ?? [];
            $item['event'][] = [
                'listen' => 'test',
                'script' => [
                    'exec' => $tests,
                    'type' => 'text/javascript',
                ],
            ];
        }

        return $item;
    }

    /**
     * Generate URL object for Postman request.
     *
     * @param  OpenApiOperationType  $operation  OpenAPI operation
     * @param  array<string, mixed>  $openapi  OpenAPI specification
     * @return array<string, mixed> Postman URL object
     */
    private function generateUrl(string $path, array $operation, array $openapi): array
    {
        // Convert path parameters
        $postmanPath = $this->formatter->convertPath($path);

        $url = [
            'raw' => '{{base_url}}'.$postmanPath,
            'host' => ['{{base_url}}'],
            'path' => array_filter(explode('/', trim($postmanPath, '/'))),
            'variable' => [],
        ];

        // Path parameters
        if (isset($operation['parameters'])) {
            foreach ($operation['parameters'] as $param) {
                if ($param['in'] === 'path') {
                    $url['variable'][] = [
                        'key' => $param['name'],
                        'value' => $this->exampleFormatter->generateExample(
                            $param['schema']['type'] ?? 'string',
                            $param['name']
                        ),
                        'description' => $param['description'] ?? '',
                    ];
                }
            }
        }

        // Query parameters
        $queryParams = $this->extractQueryParameters($operation);
        if (! empty($queryParams)) {
            $url['query'] = $queryParams;
        }

        return $url;
    }

    /**
     * Generate request body for Postman request.
     *
     * @param  array<string, mixed>  $requestBody  OpenAPI request body
     * @return array<string, mixed>|null Postman request body
     */
    private function generateRequestBody(array $requestBody): ?array
    {
        $content = $requestBody['content'] ?? [];

        // JSON format
        if (isset($content['application/json'])) {
            $schema = $content['application/json']['schema'] ?? [];
            $example = $this->exampleFormatter->generateFromSchema($schema);

            return [
                'mode' => 'raw',
                'raw' => json_encode($example, JSON_PRETTY_PRINT),
                'options' => [
                    'raw' => [
                        'language' => 'json',
                    ],
                ],
            ];
        }

        // multipart/form-data format
        if (isset($content['multipart/form-data'])) {
            $schema = $content['multipart/form-data']['schema'] ?? [];
            $formData = [];

            foreach ($schema['properties'] ?? [] as $name => $property) {
                if ($property['type'] === 'string' && ($property['format'] ?? '') === 'binary') {
                    $formData[] = [
                        'key' => $name,
                        'type' => 'file',
                        'src' => '/path/to/file',
                        'description' => $property['description'] ?? '',
                    ];
                } else {
                    $formData[] = [
                        'key' => $name,
                        'value' => $this->exampleFormatter->generateExample(
                            $property['type'] ?? 'string',
                            $name
                        ),
                        'type' => 'text',
                        'description' => $property['description'] ?? '',
                    ];
                }
            }

            return [
                'mode' => 'formdata',
                'formdata' => $formData,
            ];
        }

        return null;
    }

    /**
     * Generate test scripts for Postman request.
     *
     * @param  OpenApiOperationType  $operation  OpenAPI operation
     * @return array<int, string> Test script lines
     */
    private function generateTests(array $operation): array
    {
        $tests = [];

        // Status code test
        $tests[] = "pm.test('Status code is successful', function () {";
        $tests[] = '    pm.response.to.have.status(200);';
        $tests[] = '});';

        // Response time test
        $tests[] = '';
        $tests[] = "pm.test('Response time is less than 500ms', function () {";
        $tests[] = '    pm.expect(pm.response.responseTime).to.be.below(500);';
        $tests[] = '});';

        // Validation error test (if 422 response exists)
        if (isset($operation['responses']['422'])) {
            $tests[] = '';
            $tests[] = '// Validation error test';
            $tests[] = 'if (pm.response.code === 422) {';
            $tests[] = "    pm.test('Validation error structure', function () {";
            $tests[] = '        const jsonData = pm.response.json();';
            $tests[] = "        pm.expect(jsonData).to.have.property('message');";
            $tests[] = "        pm.expect(jsonData).to.have.property('errors');";
            $tests[] = '    });';
            $tests[] = '}';
        }

        // JSON response structure test
        if (isset($operation['responses']['200']['content']['application/json'])) {
            $schema = $operation['responses']['200']['content']['application/json']['schema'] ?? [];
            $tests[] = '';
            $tests[] = "pm.test('Response structure', function () {";
            $tests[] = '    const jsonData = pm.response.json();';

            foreach ($schema['properties'] ?? [] as $property => $spec) {
                $tests[] = "    pm.expect(jsonData).to.have.property('{$property}');";
            }

            $tests[] = '});';
        }

        return $tests;
    }

    public function getFileExtension(): string
    {
        return 'postman_collection.json';
    }

    public function getFormatName(): string
    {
        return 'Postman Collection v2.1';
    }

    /**
     * Export environment configuration for Postman.
     *
     * @param  array<int, array<string, mixed>>  $servers  OpenAPI servers
     * @param  array<int, array<string, mixed>>  $security  Security schemes
     * @return array<string, mixed> Postman environment
     */
    public function exportEnvironment(array $servers, array $security, string $environment = 'local'): array
    {
        $variables = [
            [
                'key' => 'base_url',
                'value' => $servers[0]['url'] ?? 'http://localhost/api',
                'type' => 'default',
                'enabled' => true,
            ],
        ];

        // Authentication related environment variables
        foreach ($security as $scheme) {
            if ($scheme['type'] === 'http' && $scheme['scheme'] === 'bearer') {
                $variables[] = [
                    'key' => 'bearer_token',
                    'value' => '',
                    'type' => 'secret',
                    'enabled' => true,
                ];
            } elseif ($scheme['type'] === 'apiKey') {
                $variables[] = [
                    'key' => 'api_key',
                    'value' => '',
                    'type' => 'secret',
                    'enabled' => true,
                ];
            }
        }

        // Custom environment variables
        if (function_exists('config')) {
            $customVars = config('spectrum.export.environment_variables', []);
            foreach ($customVars as $key => $value) {
                $variables[] = [
                    'key' => $key,
                    'value' => $value,
                    'type' => 'default',
                    'enabled' => true,
                ];
            }
        }

        return [
            'id' => Str::uuid()->toString(),
            'name' => "{$environment} Environment",
            'values' => $variables,
            '_postman_variable_scope' => 'environment',
            '_postman_exported_at' => now()->toIso8601String(),
            '_postman_exported_using' => 'Laravel Spectrum',
        ];
    }

    /**
     * Extract authentication configuration from OpenAPI.
     *
     * @param  array<string, mixed>  $openapi  OpenAPI specification
     * @return array<string, mixed> Authentication configuration
     */
    private function extractAuth(array $openapi): array
    {
        if (! isset($openapi['components']['securitySchemes']) || ! isset($openapi['security'])) {
            return [];
        }

        $globalSecurity = $openapi['security'];
        $securitySchemes = $openapi['components']['securitySchemes'];

        if (empty($globalSecurity)) {
            return [];
        }

        return $this->formatter->formatAuth($globalSecurity, $securitySchemes);
    }

    /**
     * Generate global pre-request scripts.
     *
     * @param  array<string, mixed>  $openapi  OpenAPI specification
     * @return array<int, array<string, mixed>> Pre-request script events
     */
    private function generatePreRequestScripts(array $openapi): array
    {
        // Global pre-request scripts can be added here
        return [];
    }

    /**
     * Generate collection variables.
     *
     * @param  array<string, mixed>  $openapi  OpenAPI specification
     * @return array<int, array<string, mixed>> Collection variables
     */
    private function generateVariables(array $openapi): array
    {
        $variables = [];

        // Add base_url variable
        $servers = $openapi['servers'] ?? [];
        if (! empty($servers)) {
            $variables[] = [
                'key' => 'base_url',
                'value' => $servers[0]['url'] ?? '',
                'type' => 'string',
            ];
        }

        return $variables;
    }

    /**
     * Group routes by their tags.
     *
     * @param  array<string, mixed>  $openapi  OpenAPI specification
     * @return array<string, array<int, array<string, mixed>>> Routes grouped by tag
     */
    private function groupRoutesByTag(array $openapi): array
    {
        $paths = $openapi['paths'] ?? [];
        if (empty($paths)) {
            return ['Default' => []];
        }

        return $this->formatter->groupRoutesByTag($paths);
    }

    /**
     * Generate headers for Postman request.
     *
     * @param  OpenApiOperationType  $operation  OpenAPI operation
     * @return array<int, array<string, mixed>> Postman headers
     */
    private function generateHeaders(array $operation): array
    {
        $headers = [];

        // Add Content-Type header for request body
        if (isset($operation['requestBody'])) {
            $contentType = $this->formatter->getContentType($operation['requestBody']);
            if ($contentType !== 'multipart/form-data') { // Postman handles multipart automatically
                $headers['Content-Type'] = $contentType;
            }
        }

        // Add Accept header
        $headers['Accept'] = 'application/json';

        // Add headers from parameters
        if (isset($operation['parameters'])) {
            foreach ($operation['parameters'] as $param) {
                if ($param['in'] === 'header') {
                    $headers[$param['name']] = $this->exampleFormatter->generateExample(
                        $param['schema']['type'] ?? 'string',
                        $param['name']
                    );
                }
            }
        }

        return $this->formatter->formatHeaders($headers);
    }

    /**
     * Generate authentication configuration for a specific operation.
     *
     * @param  array<int, array<string, array<int, string>>>  $security  Security requirements
     * @param  array<string, mixed>  $openapi  OpenAPI specification
     * @return array<string, mixed> Postman authentication
     */
    private function generateAuth(array $security, array $openapi): array
    {
        if (! isset($openapi['components']['securitySchemes'])) {
            return [];
        }

        return $this->formatter->formatAuth($security, $openapi['components']['securitySchemes']);
    }

    /**
     * Generate response examples for Postman.
     *
     * @param  array<string, array<string, mixed>>  $responses  OpenAPI responses
     * @return array<int, array<string, mixed>> Postman response examples
     */
    private function generateResponseExamples(array $responses): array
    {
        $examples = [];

        foreach ($responses as $statusCode => $response) {
            if (! isset($response['content']['application/json'])) {
                continue;
            }

            $schema = $response['content']['application/json']['schema'] ?? [];
            $exampleData = $this->exampleFormatter->generateFromSchema($schema);

            $examples[] = [
                'name' => $response['description'] ?? "Response {$statusCode}",
                'originalRequest' => [
                    'method' => 'GET',
                    'header' => [],
                    'url' => [
                        'raw' => '{{base_url}}/example',
                    ],
                ],
                'status' => $this->getStatusText($statusCode),
                'code' => (int) $statusCode,
                '_postman_previewlanguage' => 'json',
                'header' => [
                    [
                        'key' => 'Content-Type',
                        'value' => 'application/json',
                    ],
                ],
                'cookie' => [],
                'body' => json_encode($exampleData, JSON_PRETTY_PRINT),
            ];
        }

        return $examples;
    }

    private function getStatusText(string $code): string
    {
        $statuses = [
            '200' => 'OK',
            '201' => 'Created',
            '204' => 'No Content',
            '400' => 'Bad Request',
            '401' => 'Unauthorized',
            '403' => 'Forbidden',
            '404' => 'Not Found',
            '422' => 'Unprocessable Entity',
            '500' => 'Internal Server Error',
        ];

        return $statuses[$code] ?? 'Unknown';
    }

    /**
     * Extract query parameters from operation.
     *
     * @param  OpenApiOperationType  $operation  OpenAPI operation
     * @return array<int, array<string, mixed>> Query parameters
     */
    private function extractQueryParameters(array $operation): array
    {
        if (! isset($operation['parameters'])) {
            return [];
        }

        return $this->formatter->formatQueryParameters($operation['parameters']);
    }

    /**
     * Generate pre-request script for operation.
     *
     * @param  OpenApiOperationType  $operation  OpenAPI operation
     * @return array<int, string> Script lines
     */
    private function generatePreRequestScript(array $operation): array
    {
        return $this->formatter->generatePreRequestScript($operation);
    }
}
