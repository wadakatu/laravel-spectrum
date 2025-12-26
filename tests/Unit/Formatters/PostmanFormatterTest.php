<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\Formatters;

use LaravelSpectrum\Formatters\PostmanFormatter;
use LaravelSpectrum\Tests\TestCase;

class PostmanFormatterTest extends TestCase
{
    private PostmanFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new PostmanFormatter;
    }

    public function test_format_headers_converts_headers_to_postman_format(): void
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Custom-Header' => 'custom-value',
        ];

        $result = $this->formatter->formatHeaders($headers);

        $this->assertCount(3, $result);
        $this->assertEquals([
            'key' => 'Content-Type',
            'value' => 'application/json',
            'type' => 'text',
        ], $result[0]);
        $this->assertEquals([
            'key' => 'Accept',
            'value' => 'application/json',
            'type' => 'text',
        ], $result[1]);
        $this->assertEquals([
            'key' => 'X-Custom-Header',
            'value' => 'custom-value',
            'type' => 'text',
        ], $result[2]);
    }

    public function test_format_headers_handles_empty_headers(): void
    {
        $result = $this->formatter->formatHeaders([]);
        $this->assertEquals([], $result);
    }

    public function test_format_auth_returns_bearer_token_format(): void
    {
        $security = [
            ['bearerAuth' => []],
        ];
        $securitySchemes = [
            'bearerAuth' => [
                'type' => 'http',
                'scheme' => 'bearer',
            ],
        ];

        $result = $this->formatter->formatAuth($security, $securitySchemes);

        $this->assertEquals('bearer', $result['type']);
        $this->assertArrayHasKey('bearer', $result);
        $this->assertEquals('token', $result['bearer'][0]['key']);
        $this->assertEquals('{{bearer_token}}', $result['bearer'][0]['value']);
    }

    public function test_format_auth_returns_api_key_format_for_header(): void
    {
        $security = [
            ['apiKeyAuth' => []],
        ];
        $securitySchemes = [
            'apiKeyAuth' => [
                'type' => 'apiKey',
                'in' => 'header',
                'name' => 'X-API-Key',
            ],
        ];

        $result = $this->formatter->formatAuth($security, $securitySchemes);

        $this->assertEquals('apikey', $result['type']);
        $this->assertArrayHasKey('apikey', $result);

        $apiKeyConfig = collect($result['apikey'])->keyBy('key');
        $this->assertEquals('X-API-Key', $apiKeyConfig['key']['value']);
        $this->assertEquals('{{api_key}}', $apiKeyConfig['value']['value']);
        $this->assertEquals('header', $apiKeyConfig['in']['value']);
    }

    public function test_format_auth_returns_oauth2_format(): void
    {
        $security = [
            ['oauth2Auth' => ['read', 'write']],
        ];
        $securitySchemes = [
            'oauth2Auth' => [
                'type' => 'oauth2',
                'flows' => [
                    'authorizationCode' => [
                        'authorizationUrl' => 'https://example.com/oauth/authorize',
                        'tokenUrl' => 'https://example.com/oauth/token',
                        'scopes' => [
                            'read' => 'Read access',
                            'write' => 'Write access',
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->formatter->formatAuth($security, $securitySchemes);

        $this->assertEquals('oauth2', $result['type']);
        $this->assertArrayHasKey('oauth2', $result);
        $this->assertEquals('accessToken', $result['oauth2'][0]['key']);
        $this->assertEquals('{{oauth2_access_token}}', $result['oauth2'][0]['value']);
    }

    public function test_format_auth_returns_empty_array_for_unknown_scheme(): void
    {
        $security = [
            ['unknownAuth' => []],
        ];
        $securitySchemes = [
            'otherAuth' => [
                'type' => 'http',
                'scheme' => 'basic',
            ],
        ];

        $result = $this->formatter->formatAuth($security, $securitySchemes);

        $this->assertEquals([], $result);
    }

    public function test_format_auth_returns_empty_array_when_no_security(): void
    {
        $result = $this->formatter->formatAuth([], []);
        $this->assertEquals([], $result);
    }

    public function test_format_query_parameters_converts_to_postman_format(): void
    {
        $parameters = [
            [
                'name' => 'page',
                'in' => 'query',
                'required' => false,
                'description' => 'Page number',
                'schema' => ['type' => 'integer', 'example' => 1],
            ],
            [
                'name' => 'limit',
                'in' => 'query',
                'required' => true,
                'description' => 'Items per page',
                'schema' => ['type' => 'integer', 'default' => 10],
            ],
            [
                'name' => 'id',
                'in' => 'path',
                'required' => true,
                'description' => 'User ID',
            ],
        ];

        $result = $this->formatter->formatQueryParameters($parameters);

        $this->assertCount(2, $result);

        $this->assertEquals('page', $result[0]['key']);
        $this->assertEquals('1', $result[0]['value']);
        $this->assertEquals('Page number', $result[0]['description']);
        $this->assertTrue($result[0]['disabled']);

        $this->assertEquals('limit', $result[1]['key']);
        $this->assertEquals('10', $result[1]['value']);
        $this->assertFalse($result[1]['disabled']);
    }

    public function test_format_query_parameters_handles_empty_array(): void
    {
        $result = $this->formatter->formatQueryParameters([]);
        $this->assertEquals([], $result);
    }

    public function test_format_path_parameters_converts_to_postman_format(): void
    {
        $parameters = [
            [
                'name' => 'userId',
                'in' => 'path',
                'required' => true,
                'description' => 'User ID',
                'schema' => ['type' => 'integer', 'example' => 123],
            ],
            [
                'name' => 'postId',
                'in' => 'path',
                'required' => true,
                'description' => 'Post ID',
                'schema' => ['type' => 'integer'],
            ],
            [
                'name' => 'page',
                'in' => 'query',
                'required' => false,
            ],
        ];

        $result = $this->formatter->formatPathParameters($parameters);

        $this->assertCount(2, $result);

        $this->assertEquals('userId', $result[0]['key']);
        $this->assertEquals('123', $result[0]['value']);
        $this->assertEquals('User ID', $result[0]['description']);

        $this->assertEquals('postId', $result[1]['key']);
        $this->assertEquals('1', $result[1]['value']);
    }

    public function test_format_path_parameters_handles_empty_array(): void
    {
        $result = $this->formatter->formatPathParameters([]);
        $this->assertEquals([], $result);
    }

    public function test_convert_path_converts_openapi_path_to_postman_format(): void
    {
        $this->assertEquals('/users/:id', $this->formatter->convertPath('/users/{id}'));
        $this->assertEquals('/users/:userId/posts/:postId', $this->formatter->convertPath('/users/{userId}/posts/{postId}'));
        $this->assertEquals('/users', $this->formatter->convertPath('/users'));
        $this->assertEquals('/users/:id/comments/:commentId', $this->formatter->convertPath('/users/{id}/comments/{commentId}'));
    }

    public function test_generate_pre_request_script_includes_request_id(): void
    {
        $operation = [
            'operationId' => 'getUsers',
            'parameters' => [],
        ];

        $result = $this->formatter->generatePreRequestScript($operation);

        $this->assertContains("pm.variables.set('request_id', pm.variables.replaceIn('{{".'$guid}}'."'));", $result);
    }

    public function test_generate_pre_request_script_includes_timestamp_when_needed(): void
    {
        $operation = [
            'operationId' => 'createEvent',
            'parameters' => [
                [
                    'name' => 'timestamp',
                    'in' => 'query',
                ],
            ],
        ];

        $result = $this->formatter->generatePreRequestScript($operation);

        $this->assertContains("pm.variables.set('timestamp', new Date().toISOString());", $result);
    }

    public function test_generate_pre_request_script_includes_timestamp_for_date_parameter(): void
    {
        $operation = [
            'operationId' => 'getReport',
            'parameters' => [
                [
                    'name' => 'start_date',
                    'in' => 'query',
                ],
            ],
        ];

        $result = $this->formatter->generatePreRequestScript($operation);

        $this->assertContains("pm.variables.set('timestamp', new Date().toISOString());", $result);
    }

    public function test_group_routes_by_tag(): void
    {
        $paths = [
            '/users' => [
                'get' => [
                    'tags' => ['Users'],
                    'operationId' => 'getUsers',
                ],
                'post' => [
                    'tags' => ['Users'],
                    'operationId' => 'createUser',
                ],
            ],
            '/posts' => [
                'get' => [
                    'tags' => ['Posts'],
                    'operationId' => 'getPosts',
                ],
            ],
            '/comments' => [
                'get' => [
                    'operationId' => 'getComments',
                ],
            ],
        ];

        $result = $this->formatter->groupRoutesByTag($paths);

        $this->assertArrayHasKey('Users', $result);
        $this->assertArrayHasKey('Posts', $result);
        $this->assertArrayHasKey('Default', $result);

        $this->assertCount(2, $result['Users']);
        $this->assertCount(1, $result['Posts']);
        $this->assertCount(1, $result['Default']);

        $this->assertEquals('/users', $result['Users'][0]['path']);
        $this->assertEquals('get', $result['Users'][0]['method']);
    }

    public function test_group_routes_by_tag_ignores_non_http_methods(): void
    {
        $paths = [
            '/users' => [
                'get' => ['tags' => ['Users'], 'operationId' => 'getUsers'],
                'parameters' => [['name' => 'id', 'in' => 'path']],
                'summary' => 'User endpoints',
            ],
        ];

        $result = $this->formatter->groupRoutesByTag($paths);

        $this->assertArrayHasKey('Users', $result);
        $this->assertCount(1, $result['Users']);
    }

    public function test_get_content_type_returns_json_by_default(): void
    {
        $requestBody = [
            'content' => [
                'application/json' => [
                    'schema' => ['type' => 'object'],
                ],
            ],
        ];

        $result = $this->formatter->getContentType($requestBody);

        $this->assertEquals('application/json', $result);
    }

    public function test_get_content_type_returns_multipart_form_data(): void
    {
        $requestBody = [
            'content' => [
                'multipart/form-data' => [
                    'schema' => ['type' => 'object'],
                ],
            ],
        ];

        $result = $this->formatter->getContentType($requestBody);

        $this->assertEquals('multipart/form-data', $result);
    }

    public function test_get_content_type_returns_form_urlencoded(): void
    {
        $requestBody = [
            'content' => [
                'application/x-www-form-urlencoded' => [
                    'schema' => ['type' => 'object'],
                ],
            ],
        ];

        $result = $this->formatter->getContentType($requestBody);

        $this->assertEquals('application/x-www-form-urlencoded', $result);
    }

    public function test_get_content_type_returns_text_plain(): void
    {
        $requestBody = [
            'content' => [
                'text/plain' => [
                    'schema' => ['type' => 'string'],
                ],
            ],
        ];

        $result = $this->formatter->getContentType($requestBody);

        $this->assertEquals('text/plain', $result);
    }

    public function test_get_content_type_returns_json_for_empty_content(): void
    {
        $requestBody = [];

        $result = $this->formatter->getContentType($requestBody);

        $this->assertEquals('application/json', $result);
    }

    public function test_get_content_type_prefers_json_over_others(): void
    {
        $requestBody = [
            'content' => [
                'application/json' => ['schema' => ['type' => 'object']],
                'multipart/form-data' => ['schema' => ['type' => 'object']],
            ],
        ];

        $result = $this->formatter->getContentType($requestBody);

        $this->assertEquals('application/json', $result);
    }

    public function test_format_query_parameters_uses_enum_first_value(): void
    {
        $parameters = [
            [
                'name' => 'status',
                'in' => 'query',
                'required' => false,
                'schema' => [
                    'type' => 'string',
                    'enum' => ['active', 'inactive', 'pending'],
                ],
            ],
        ];

        $result = $this->formatter->formatQueryParameters($parameters);

        $this->assertEquals('active', $result[0]['value']);
    }

    public function test_format_query_parameters_handles_boolean_type(): void
    {
        $parameters = [
            [
                'name' => 'active',
                'in' => 'query',
                'required' => false,
                'schema' => ['type' => 'boolean'],
            ],
        ];

        $result = $this->formatter->formatQueryParameters($parameters);

        $this->assertEquals('true', $result[0]['value']);
    }

    public function test_format_query_parameters_handles_number_type(): void
    {
        $parameters = [
            [
                'name' => 'price',
                'in' => 'query',
                'required' => false,
                'schema' => ['type' => 'number'],
            ],
        ];

        $result = $this->formatter->formatQueryParameters($parameters);

        $this->assertEquals('1.0', $result[0]['value']);
    }

    public function test_format_query_parameters_handles_array_type(): void
    {
        $parameters = [
            [
                'name' => 'tags',
                'in' => 'query',
                'required' => false,
                'schema' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ];

        $result = $this->formatter->formatQueryParameters($parameters);

        $this->assertEquals('[]', $result[0]['value']);
    }

    public function test_format_query_parameters_uses_direct_example(): void
    {
        $parameters = [
            [
                'name' => 'filter',
                'in' => 'query',
                'required' => false,
                'example' => 'custom-filter-value',
                'schema' => ['type' => 'string'],
            ],
        ];

        $result = $this->formatter->formatQueryParameters($parameters);

        $this->assertEquals('custom-filter-value', $result[0]['value']);
    }

    public function test_format_query_parameters_handles_array_default_value(): void
    {
        $parameters = [
            [
                'name' => 'ids',
                'in' => 'query',
                'required' => false,
                'schema' => [
                    'type' => 'array',
                    'default' => [1, 2, 3],
                ],
            ],
        ];

        $result = $this->formatter->formatQueryParameters($parameters);

        $this->assertEquals('[1,2,3]', $result[0]['value']);
    }

    public function test_group_routes_by_tag_handles_all_http_methods(): void
    {
        $paths = [
            '/resource' => [
                'get' => ['tags' => ['Resource'], 'operationId' => 'get'],
                'post' => ['tags' => ['Resource'], 'operationId' => 'post'],
                'put' => ['tags' => ['Resource'], 'operationId' => 'put'],
                'patch' => ['tags' => ['Resource'], 'operationId' => 'patch'],
                'delete' => ['tags' => ['Resource'], 'operationId' => 'delete'],
                'head' => ['tags' => ['Resource'], 'operationId' => 'head'],
                'options' => ['tags' => ['Resource'], 'operationId' => 'options'],
            ],
        ];

        $result = $this->formatter->groupRoutesByTag($paths);

        $this->assertCount(7, $result['Resource']);

        $methods = array_column($result['Resource'], 'method');
        $this->assertContains('get', $methods);
        $this->assertContains('post', $methods);
        $this->assertContains('put', $methods);
        $this->assertContains('patch', $methods);
        $this->assertContains('delete', $methods);
        $this->assertContains('head', $methods);
        $this->assertContains('options', $methods);
    }
}
