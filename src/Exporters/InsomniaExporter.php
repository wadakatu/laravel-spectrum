<?php

namespace LaravelSpectrum\Exporters;

use LaravelSpectrum\Formatters\InsomniaFormatter;
use LaravelSpectrum\Formatters\RequestExampleFormatter;

class InsomniaExporter implements ExportFormatInterface
{
    private InsomniaFormatter $formatter;

    private RequestExampleFormatter $exampleFormatter;

    public function __construct(
        InsomniaFormatter $formatter,
        RequestExampleFormatter $exampleFormatter
    ) {
        $this->formatter = $formatter;
        $this->exampleFormatter = $exampleFormatter;
    }

    /**
     * Export OpenAPI specification to Insomnia format.
     *
     * @param  array<string, mixed>  $openapi
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function export(array $openapi, array $options = []): array
    {
        $exportData = [
            '_type' => 'export',
            '__export_format' => 4,
            '__export_date' => now()->toIso8601String(),
            '__export_source' => 'laravel-spectrum',
            'resources' => [],
        ];

        // Create workspace
        $workspaceId = $this->generateId('wrk');
        $exportData['resources'][] = [
            '_id' => $workspaceId,
            '_type' => 'workspace',
            'name' => $openapi['info']['title'] ?? 'API Workspace',
            'description' => $openapi['info']['description'] ?? '',
            'scope' => 'collection',
        ];

        // Create environment
        $environmentId = $this->generateId('env');
        $exportData['resources'][] = $this->createEnvironment($environmentId, $workspaceId, $openapi);

        // Create base environment
        $baseEnvironmentId = $this->generateId('env');
        $exportData['resources'][] = [
            '_id' => $baseEnvironmentId,
            '_type' => 'environment',
            'parentId' => $workspaceId,
            'name' => 'Base Environment',
            'data' => $this->formatter->generateEnvironmentData($openapi['servers'] ?? []),
            'dataPropertyOrder' => [
                '&base_url',
                '&scheme',
                '&host',
                '&base_path',
            ],
            'color' => null,
            'isPrivate' => false,
            'metaSortKey' => 1,
        ];

        // Create request groups and requests
        $groups = $this->groupRoutesByTag($openapi);
        $sortKey = 0;

        foreach ($groups as $tag => $routes) {
            $groupId = $this->generateId('fld');

            // Create folder
            $exportData['resources'][] = [
                '_id' => $groupId,
                '_type' => 'request_group',
                'parentId' => $workspaceId,
                'name' => $tag,
                'description' => '',
                'environment' => [],
                'environmentPropertyOrder' => null,
                'metaSortKey' => $sortKey++,
            ];

            // Create requests
            foreach ($routes as $route) {
                $exportData['resources'][] = $this->createRequest(
                    $route,
                    $groupId,
                    $environmentId,
                    $sortKey++
                );
            }
        }

        return $exportData;
    }

    /**
     * Create a request resource.
     *
     * @param  array<string, mixed>  $route
     * @return array<string, mixed>
     */
    private function createRequest(array $route, string $parentId, string $environmentId, int $sortKey): array
    {
        $path = $route['path'];
        $method = strtolower($route['method']);
        $operation = $route['operation'];

        // Convert path parameters
        $insomniaPath = $this->formatter->convertPath($path);

        $request = [
            '_id' => $this->generateId('req'),
            '_type' => 'request',
            'parentId' => $parentId,
            'name' => $operation['summary'] ?? "{$method} {$path}",
            'description' => $operation['description'] ?? '',
            'url' => '{{ _.base_url }}'.$insomniaPath,
            'method' => strtoupper($method),
            'body' => $this->generateBody($operation),
            'parameters' => $this->generateParameters($operation),
            'headers' => $this->generateHeaders($operation),
            'authentication' => $this->generateAuthentication($operation),
            'metaSortKey' => $sortKey,
            'isPrivate' => false,
            'settingStoreCookies' => true,
            'settingSendCookies' => true,
            'settingDisableRenderRequestBody' => false,
            'settingEncodeUrl' => true,
            'settingRebuildPath' => true,
            'settingFollowRedirects' => 'global',
        ];

        return $request;
    }

    /**
     * Generate request body.
     *
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function generateBody(array $operation): array
    {
        if (! isset($operation['requestBody'])) {
            return [];
        }

        $content = $operation['requestBody']['content'] ?? [];

        // JSON format
        if (isset($content['application/json'])) {
            $schema = $content['application/json']['schema'] ?? [];
            $example = $this->exampleFormatter->generateFromSchema($schema);

            return [
                'mimeType' => 'application/json',
                'text' => json_encode($example, JSON_PRETTY_PRINT),
            ];
        }

        // Form data format
        if (isset($content['multipart/form-data'])) {
            $schema = $content['multipart/form-data']['schema'] ?? [];
            $params = $this->formatter->formatFormDataParameters(
                $schema['properties'] ?? [],
                $this->exampleFormatter
            );

            return [
                'mimeType' => 'multipart/form-data',
                'params' => $params,
            ];
        }

        return [];
    }

    /**
     * Generate query parameters.
     *
     * @param  array<string, mixed>  $operation
     * @return array<int, array<string, mixed>>
     */
    private function generateParameters(array $operation): array
    {
        if (! isset($operation['parameters'])) {
            return [];
        }

        return $this->formatter->formatQueryParameters($operation['parameters']);
    }

    /**
     * Generate authentication configuration.
     *
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function generateAuthentication(array $operation): array
    {
        if (! isset($operation['security'])) {
            return [];
        }

        // Bearer Token authentication
        foreach ($operation['security'] as $security) {
            foreach ($security as $key => $scopes) {
                $authType = $this->formatter->detectAuthType($key);

                if ($authType === 'bearer') {
                    return [
                        'type' => 'bearer',
                        'token' => '{{ _.bearer_token }}',
                        'prefix' => 'Bearer',
                    ];
                }

                if ($authType === 'apikey') {
                    return [
                        'type' => 'apikey',
                        'key' => '{{ _.api_key }}',
                    ];
                }
            }
        }

        return [];
    }

    private function generateId(string $prefix): string
    {
        return $this->formatter->generateId($prefix);
    }

    public function getFileExtension(): string
    {
        return 'insomnia.json';
    }

    public function getFormatName(): string
    {
        return 'Insomnia Export v4';
    }

    /**
     * Export environment configuration.
     *
     * @param  array<int, array<string, mixed>>  $servers
     * @param  array<int, array<string, mixed>>  $security
     * @return array<string, mixed>
     */
    public function exportEnvironment(array $servers, array $security, string $environment = 'local'): array
    {
        // Insomnia environments are included in the main export
        return [];
    }

    /**
     * Create environment resource.
     *
     * @param  array<string, mixed>  $openapi
     * @return array<string, mixed>
     */
    private function createEnvironment(string $environmentId, string $workspaceId, array $openapi): array
    {
        $data = [
            'base_url' => '{{ _.scheme }}://{{ _.host }}{{ _.base_path }}',
            'scheme' => 'https',
            'host' => 'api.example.com',
            'base_path' => '',
        ];

        // Add authentication variables
        $security = $openapi['components']['securitySchemes'] ?? [];
        foreach ($security as $name => $scheme) {
            if ($scheme['type'] === 'http' && $scheme['scheme'] === 'bearer') {
                $data['bearer_token'] = '';
            } elseif ($scheme['type'] === 'apiKey') {
                $data['api_key'] = '';
            } elseif ($scheme['type'] === 'oauth2') {
                $data['oauth2_client_id'] = '';
                $data['oauth2_client_secret'] = '';
                $data['oauth2_token_url'] = '';
                $data['oauth2_auth_url'] = '';
            }
        }

        // Add custom environment variables
        if (function_exists('config')) {
            $customVars = config('spectrum.export.environment_variables', []);
            foreach ($customVars as $key => $value) {
                $data[$key] = $value;
            }
        }

        return [
            '_id' => $environmentId,
            '_type' => 'environment',
            'parentId' => $workspaceId,
            'name' => 'Production Environment',
            'data' => $data,
            'dataPropertyOrder' => array_map(fn ($key) => "&{$key}", array_keys($data)),
            'color' => '#7d69cb',
            'isPrivate' => false,
            'metaSortKey' => 0,
        ];
    }

    /**
     * Group routes by tag.
     *
     * @param  array<string, mixed>  $openapi
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function groupRoutesByTag(array $openapi): array
    {
        return $this->formatter->groupRoutesByTag($openapi['paths'] ?? []);
    }

    /**
     * Generate request headers.
     *
     * @param  array<string, mixed>  $operation
     * @return array<int, array<string, string>>
     */
    private function generateHeaders(array $operation): array
    {
        $headers = [];

        // Add Content-Type header for request body
        if (isset($operation['requestBody'])) {
            $contentType = $this->formatter->getContentType($operation['requestBody']);
            if ($contentType !== 'multipart/form-data') { // Insomnia handles multipart automatically
                $headers[] = [
                    'name' => 'Content-Type',
                    'value' => $contentType,
                ];
            }
        }

        // Add Accept header
        $headers[] = [
            'name' => 'Accept',
            'value' => 'application/json',
        ];

        // Add headers from parameters
        if (isset($operation['parameters'])) {
            foreach ($operation['parameters'] as $param) {
                if ($param['in'] === 'header') {
                    $headers[] = [
                        'name' => $param['name'],
                        'value' => $this->exampleFormatter->generateExample(
                            $param['schema']['type'] ?? 'string',
                            $param['name']
                        ),
                    ];
                }
            }
        }

        return $headers;
    }
}
