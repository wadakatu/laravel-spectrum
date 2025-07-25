<?php

namespace LaravelSpectrum\Tests\Unit\Exporters;

use LaravelSpectrum\Exporters\PostmanExporter;
use LaravelSpectrum\Formatters\PostmanFormatter;
use LaravelSpectrum\Formatters\RequestExampleFormatter;
use LaravelSpectrum\Tests\TestCase;

class PostmanExporterTest extends TestCase
{
    private PostmanExporter $exporter;

    private PostmanFormatter $formatter;

    private RequestExampleFormatter $exampleFormatter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->formatter = new PostmanFormatter;
        $this->exampleFormatter = new RequestExampleFormatter;
        $this->exporter = new PostmanExporter($this->formatter, $this->exampleFormatter);
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

        $this->assertArrayHasKey('info', $result);
        $this->assertArrayHasKey('item', $result);
        $this->assertEquals('Test API', $result['info']['name']);
        $this->assertEquals('Test API Description', $result['info']['description']);
        $this->assertStringContainsString('https://schema.getpostman.com/json/collection/v2.1.0/collection.json', $result['info']['schema']);
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

        $this->assertArrayHasKey('auth', $result);
        $this->assertNotEmpty($result['auth']);
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

        $this->assertCount(1, $result['item']);
        $folder = $result['item'][0];
        $request = $folder['item'][0];

        $this->assertArrayHasKey('body', $request['request']);
        $this->assertEquals('raw', $request['request']['body']['mode']);
        $this->assertJson($request['request']['body']['raw']);
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
                            ],
                            [
                                'name' => 'limit',
                                'in' => 'query',
                                'schema' => ['type' => 'integer'],
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

        $request = $result['item'][0]['item'][0];
        $url = $request['request']['url'];

        $this->assertArrayHasKey('query', $url);
        $this->assertCount(2, $url['query']);
    }

    public function test_export_environment(): void
    {
        $servers = [['url' => 'https://api.example.com/v1']];
        $security = [
            [
                'type' => 'http',
                'scheme' => 'bearer',
            ],
        ];

        $result = $this->exporter->exportEnvironment($servers, $security, 'production');

        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('values', $result);
        $this->assertEquals('production Environment', $result['name']);

        $variables = array_column($result['values'], 'key');
        $this->assertContains('base_url', $variables);
        $this->assertContains('bearer_token', $variables);
    }

    public function test_get_file_extension(): void
    {
        $this->assertEquals('postman_collection.json', $this->exporter->getFileExtension());
    }

    public function test_get_format_name(): void
    {
        $this->assertEquals('Postman Collection v2.1', $this->exporter->getFormatName());
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

        $request = $result['item'][0]['item'][0];
        $url = $request['request']['url'];

        $this->assertStringContainsString(':id', $url['raw']);
        $this->assertArrayHasKey('variable', $url);
        $this->assertEquals('id', $url['variable'][0]['key']);
        $this->assertIsString($url['variable'][0]['value']);
        $this->assertNotEmpty($url['variable'][0]['value']);
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

        $request = $result['item'][0]['item'][0];
        $body = $request['request']['body'];

        $this->assertEquals('formdata', $body['mode']);
        $this->assertCount(2, $body['formdata']);

        $fileField = $body['formdata'][0];
        $this->assertEquals('file', $fileField['key']);
        $this->assertEquals('file', $fileField['type']);

        $textField = $body['formdata'][1];
        $this->assertEquals('description', $textField['key']);
        $this->assertEquals('text', $textField['type']);
    }

    public function test_export_generates_test_scripts(): void
    {
        $openapi = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API'],
            'paths' => [
                '/users' => [
                    'post' => [
                        'summary' => 'Create user',
                        'tags' => ['Users'],
                        'responses' => [
                            '200' => [
                                'description' => 'Success',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'id' => ['type' => 'integer'],
                                                'name' => ['type' => 'string'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            '422' => [
                                'description' => 'Validation error',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->exporter->export($openapi);

        $request = $result['item'][0]['item'][0];

        $this->assertArrayHasKey('event', $request);

        $testEvent = null;
        foreach ($request['event'] as $event) {
            if ($event['listen'] === 'test') {
                $testEvent = $event;
                break;
            }
        }

        $this->assertNotNull($testEvent);
        $this->assertArrayHasKey('script', $testEvent);
        $this->assertArrayHasKey('exec', $testEvent['script']);

        $testScript = implode("\n", $testEvent['script']['exec']);
        $this->assertStringContainsString('pm.test', $testScript);
        $this->assertStringContainsString('Status code is successful', $testScript);
        $this->assertStringContainsString('Response time is less than 500ms', $testScript);
        $this->assertStringContainsString('Validation error structure', $testScript);
    }
}
