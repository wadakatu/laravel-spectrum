<?php

namespace LaravelSpectrum\Tests\Unit\Exporters;

use LaravelSpectrum\Exporters\InsomniaExporter;
use LaravelSpectrum\Formatters\InsomniaFormatter;
use LaravelSpectrum\Formatters\RequestExampleFormatter;
use LaravelSpectrum\Tests\TestCase;

class InsomniaExporterTest extends TestCase
{
    private InsomniaExporter $exporter;

    private InsomniaFormatter $formatter;

    private RequestExampleFormatter $exampleFormatter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->formatter = new InsomniaFormatter;
        $this->exampleFormatter = new RequestExampleFormatter;
        $this->exporter = new InsomniaExporter($this->formatter, $this->exampleFormatter);
    }

    public function test_export_basic_collection(): void
    {
        $openapi = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Test API',
                'description' => 'Test API Description',
                'version' => '1.0.0',
            ],
            'servers' => [
                ['url' => 'https://api.example.com'],
            ],
            'paths' => [
                '/users' => [
                    'get' => [
                        'summary' => 'Get users',
                        'tags' => ['Users'],
                        'responses' => [
                            '200' => [
                                'description' => 'Success',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->exporter->export($openapi);

        $this->assertArrayHasKey('_type', $result);
        $this->assertEquals('export', $result['_type']);
        $this->assertEquals(4, $result['__export_format']);
        $this->assertEquals('laravel-spectrum', $result['__export_source']);
        $this->assertArrayHasKey('resources', $result);

        // Check workspace creation
        $workspace = array_filter($result['resources'], fn ($r) => $r['_type'] === 'workspace');
        $this->assertCount(1, $workspace);
        $workspace = array_values($workspace)[0];
        $this->assertEquals('Test API', $workspace['name']);
        $this->assertEquals('Test API Description', $workspace['description']);
    }

    public function test_export_with_authentication(): void
    {
        $openapi = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Test API',
            ],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                    ],
                ],
            ],
            'security' => [
                ['bearerAuth' => []],
            ],
            'paths' => [
                '/protected' => [
                    'get' => [
                        'summary' => 'Protected endpoint',
                        'tags' => ['Protected'],
                        'security' => [
                            ['bearerAuth' => []],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Success'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->exporter->export($openapi);

        // Find request resource
        $request = array_filter($result['resources'], fn ($r) => $r['_type'] === 'request');
        $this->assertCount(1, $request);
        $request = array_values($request)[0];

        $this->assertArrayHasKey('authentication', $request);
        $this->assertEquals('bearer', $request['authentication']['type']);
        $this->assertEquals('{{ _.bearer_token }}', $request['authentication']['token']);
    }

    public function test_export_with_request_body(): void
    {
        $openapi = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API'],
            'paths' => [
                '/users' => [
                    'post' => [
                        'summary' => 'Create user',
                        'tags' => ['Users'],
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'name' => ['type' => 'string'],
                                            'email' => ['type' => 'string'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '201' => ['description' => 'Created'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->exporter->export($openapi);

        $request = array_filter($result['resources'], fn ($r) => $r['_type'] === 'request');
        $request = array_values($request)[0];

        $this->assertArrayHasKey('body', $request);
        $this->assertEquals('application/json', $request['body']['mimeType']);
        $this->assertJson($request['body']['text']);
    }

    public function test_export_with_query_parameters(): void
    {
        $openapi = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API'],
            'paths' => [
                '/users' => [
                    'get' => [
                        'summary' => 'Get users',
                        'tags' => ['Users'],
                        'parameters' => [
                            [
                                'name' => 'page',
                                'in' => 'query',
                                'schema' => ['type' => 'integer'],
                                'required' => false,
                            ],
                            [
                                'name' => 'limit',
                                'in' => 'query',
                                'schema' => ['type' => 'integer'],
                                'required' => true,
                            ],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Success'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->exporter->export($openapi);

        $request = array_filter($result['resources'], fn ($r) => $r['_type'] === 'request');
        $request = array_values($request)[0];

        $this->assertArrayHasKey('parameters', $request);
        $this->assertCount(2, $request['parameters']);

        // Check page parameter (not required)
        $pageParam = $request['parameters'][0];
        $this->assertEquals('page', $pageParam['name']);
        $this->assertNotEmpty($pageParam['value']);
        $this->assertTrue($pageParam['disabled']);

        // Check limit parameter (required)
        $limitParam = $request['parameters'][1];
        $this->assertEquals('limit', $limitParam['name']);
        $this->assertNotEmpty($limitParam['value']);
        $this->assertFalse($limitParam['disabled']);
    }

    public function test_get_file_extension(): void
    {
        $this->assertEquals('insomnia.json', $this->exporter->getFileExtension());
    }

    public function test_get_format_name(): void
    {
        $this->assertEquals('Insomnia Export v4', $this->exporter->getFormatName());
    }

    public function test_export_with_path_parameters(): void
    {
        $openapi = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API'],
            'paths' => [
                '/users/{id}' => [
                    'get' => [
                        'summary' => 'Get user by ID',
                        'tags' => ['Users'],
                        'parameters' => [
                            [
                                'name' => 'id',
                                'in' => 'path',
                                'required' => true,
                                'schema' => ['type' => 'string'],
                            ],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Success'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->exporter->export($openapi);

        $request = array_filter($result['resources'], fn ($r) => $r['_type'] === 'request');
        $request = array_values($request)[0];

        $this->assertStringContainsString('{{ _.id }}', $request['url']);
    }

    public function test_export_with_multipart_form_data(): void
    {
        $openapi = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API'],
            'paths' => [
                '/upload' => [
                    'post' => [
                        'summary' => 'Upload file',
                        'tags' => ['Upload'],
                        'requestBody' => [
                            'content' => [
                                'multipart/form-data' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'file' => [
                                                'type' => 'string',
                                                'format' => 'binary',
                                            ],
                                            'description' => [
                                                'type' => 'string',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Success'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->exporter->export($openapi);

        $request = array_filter($result['resources'], fn ($r) => $r['_type'] === 'request');
        $request = array_values($request)[0];

        $this->assertEquals('multipart/form-data', $request['body']['mimeType']);
        $this->assertCount(2, $request['body']['params']);

        $fileField = $request['body']['params'][0];
        $this->assertEquals('file', $fileField['name']);
        $this->assertEquals('file', $fileField['type']);

        $textField = $request['body']['params'][1];
        $this->assertEquals('description', $textField['name']);
        $this->assertIsString($textField['value']);
        $this->assertNotEmpty($textField['value']);
    }

    public function test_export_creates_environment(): void
    {
        $openapi = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API'],
            'servers' => [
                ['url' => 'https://api.example.com/v1'],
            ],
            'paths' => [
                '/test' => [
                    'get' => [
                        'summary' => 'Test endpoint',
                        'tags' => ['Test'],
                        'responses' => [
                            '200' => ['description' => 'Success'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->exporter->export($openapi);

        // Check base environment
        $baseEnv = array_filter($result['resources'], fn ($r) => $r['_type'] === 'environment' && $r['name'] === 'Base Environment'
        );
        $this->assertCount(1, $baseEnv);

        $baseEnv = array_values($baseEnv)[0];
        $this->assertArrayHasKey('data', $baseEnv);
        $this->assertArrayHasKey('base_url', $baseEnv['data']);
        $this->assertArrayHasKey('scheme', $baseEnv['data']);
        $this->assertArrayHasKey('host', $baseEnv['data']);
        $this->assertArrayHasKey('base_path', $baseEnv['data']);
    }

    public function test_export_with_api_key_authentication(): void
    {
        $openapi = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API'],
            'components' => [
                'securitySchemes' => [
                    'apiKey' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'X-API-Key',
                    ],
                ],
            ],
            'paths' => [
                '/protected' => [
                    'get' => [
                        'summary' => 'Protected endpoint',
                        'tags' => ['Protected'],
                        'security' => [
                            ['apiKey' => []],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Success'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->exporter->export($openapi);

        $request = array_filter($result['resources'], fn ($r) => $r['_type'] === 'request');
        $request = array_values($request)[0];

        $this->assertArrayHasKey('authentication', $request);
        $this->assertEquals('apikey', $request['authentication']['type']);
        $this->assertEquals('{{ _.api_key }}', $request['authentication']['key']);
    }

    public function test_export_groups_by_tags(): void
    {
        $openapi = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API'],
            'paths' => [
                '/users' => [
                    'get' => [
                        'summary' => 'Get users',
                        'tags' => ['Users'],
                        'responses' => [
                            '200' => ['description' => 'Success'],
                        ],
                    ],
                ],
                '/posts' => [
                    'get' => [
                        'summary' => 'Get posts',
                        'tags' => ['Posts'],
                        'responses' => [
                            '200' => ['description' => 'Success'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->exporter->export($openapi);

        $groups = array_filter($result['resources'], fn ($r) => $r['_type'] === 'request_group');
        $this->assertCount(2, $groups);

        $groupNames = array_column(array_values($groups), 'name');
        $this->assertContains('Users', $groupNames);
        $this->assertContains('Posts', $groupNames);
    }

    public function test_export_environment_returns_empty_array(): void
    {
        // Insomnia environments are included in the main export, so this method returns empty
        $result = $this->exporter->exportEnvironment(
            [['url' => 'https://api.example.com']],
            [['bearerAuth' => []]],
            'local'
        );

        $this->assertEmpty($result);
    }

    public function test_export_with_unsupported_request_body_content_type(): void
    {
        // Test when request body has unsupported content type (not JSON or multipart)
        $openapi = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API'],
            'paths' => [
                '/users' => [
                    'post' => [
                        'summary' => 'Create user',
                        'tags' => ['Users'],
                        'requestBody' => [
                            'content' => [
                                'text/plain' => [
                                    'schema' => [
                                        'type' => 'string',
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '201' => ['description' => 'Created'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->exporter->export($openapi);

        $request = array_filter($result['resources'], fn ($r) => $r['_type'] === 'request');
        $request = array_values($request)[0];

        // Body should be empty for unsupported content types
        $this->assertEmpty($request['body']);
    }

    public function test_export_with_unrecognized_security_type(): void
    {
        // Test when security is present but auth type is not recognized
        $openapi = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API'],
            'components' => [
                'securitySchemes' => [
                    'unknown' => [
                        'type' => 'custom',
                    ],
                ],
            ],
            'paths' => [
                '/protected' => [
                    'get' => [
                        'summary' => 'Protected endpoint',
                        'tags' => ['Protected'],
                        'security' => [
                            ['unknown' => []],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Success'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->exporter->export($openapi);

        $request = array_filter($result['resources'], fn ($r) => $r['_type'] === 'request');
        $request = array_values($request)[0];

        // Authentication should be empty for unrecognized types
        $this->assertArrayHasKey('authentication', $request);
        $this->assertEmpty($request['authentication']);
    }

    public function test_export_with_oauth2_security_scheme(): void
    {
        // Test OAuth2 security scheme adds proper environment variables
        $openapi = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API'],
            'components' => [
                'securitySchemes' => [
                    'oauth2' => [
                        'type' => 'oauth2',
                        'flows' => [
                            'authorizationCode' => [
                                'authorizationUrl' => 'https://auth.example.com/authorize',
                                'tokenUrl' => 'https://auth.example.com/token',
                                'scopes' => [],
                            ],
                        ],
                    ],
                ],
            ],
            'paths' => [
                '/test' => [
                    'get' => [
                        'summary' => 'Test endpoint',
                        'tags' => ['Test'],
                        'responses' => [
                            '200' => ['description' => 'Success'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->exporter->export($openapi);

        // Find environment resource
        $env = array_filter($result['resources'], fn ($r) => $r['_type'] === 'environment' && $r['name'] === 'Production Environment'
        );
        $this->assertCount(1, $env);

        $env = array_values($env)[0];
        $this->assertArrayHasKey('data', $env);

        // OAuth2 environment variables should be present
        $this->assertArrayHasKey('oauth2_client_id', $env['data']);
        $this->assertArrayHasKey('oauth2_client_secret', $env['data']);
        $this->assertArrayHasKey('oauth2_token_url', $env['data']);
        $this->assertArrayHasKey('oauth2_auth_url', $env['data']);
    }

    public function test_export_with_custom_environment_variables(): void
    {
        // Test custom environment variables from config
        config(['spectrum.export.environment_variables' => [
            'custom_var' => 'custom_value',
            'api_version' => 'v2',
        ]]);

        $openapi = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API'],
            'paths' => [
                '/test' => [
                    'get' => [
                        'summary' => 'Test endpoint',
                        'tags' => ['Test'],
                        'responses' => [
                            '200' => ['description' => 'Success'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->exporter->export($openapi);

        // Find environment resource
        $env = array_filter($result['resources'], fn ($r) => $r['_type'] === 'environment' && $r['name'] === 'Production Environment'
        );
        $this->assertCount(1, $env);

        $env = array_values($env)[0];
        $this->assertArrayHasKey('data', $env);

        // Custom environment variables should be present
        $this->assertArrayHasKey('custom_var', $env['data']);
        $this->assertEquals('custom_value', $env['data']['custom_var']);
        $this->assertArrayHasKey('api_version', $env['data']);
        $this->assertEquals('v2', $env['data']['api_version']);
    }

    public function test_export_with_header_parameters(): void
    {
        // Test header parameters are added to headers
        $openapi = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API'],
            'paths' => [
                '/users' => [
                    'get' => [
                        'summary' => 'Get users',
                        'tags' => ['Users'],
                        'parameters' => [
                            [
                                'name' => 'X-Custom-Header',
                                'in' => 'header',
                                'schema' => ['type' => 'string'],
                                'required' => true,
                            ],
                            [
                                'name' => 'X-Correlation-Id',
                                'in' => 'header',
                                'schema' => ['type' => 'string'],
                                'required' => false,
                            ],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Success'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->exporter->export($openapi);

        $request = array_filter($result['resources'], fn ($r) => $r['_type'] === 'request');
        $request = array_values($request)[0];

        $this->assertArrayHasKey('headers', $request);

        // Check that header parameters are included
        $headerNames = array_column($request['headers'], 'name');
        $this->assertContains('X-Custom-Header', $headerNames);
        $this->assertContains('X-Correlation-Id', $headerNames);
        $this->assertContains('Accept', $headerNames); // Default header
    }

    public function test_export_with_no_tags_uses_default_group(): void
    {
        $openapi = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API'],
            'paths' => [
                '/users' => [
                    'get' => [
                        'summary' => 'Get users',
                        // No tags
                        'responses' => [
                            '200' => ['description' => 'Success'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->exporter->export($openapi);

        $groups = array_filter($result['resources'], fn ($r) => $r['_type'] === 'request_group');
        $this->assertCount(1, $groups);

        $group = array_values($groups)[0];
        $this->assertEquals('Default', $group['name']);
    }
}
