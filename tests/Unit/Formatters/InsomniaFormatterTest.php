<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\Formatters;

use LaravelSpectrum\Formatters\InsomniaFormatter;
use LaravelSpectrum\Formatters\RequestExampleFormatter;
use LaravelSpectrum\Tests\TestCase;

class InsomniaFormatterTest extends TestCase
{
    private InsomniaFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new InsomniaFormatter;
    }

    public function test_generate_id_creates_unique_id_with_prefix(): void
    {
        $id1 = $this->formatter->generateId('req');
        $id2 = $this->formatter->generateId('req');

        $this->assertStringStartsWith('req_', $id1);
        $this->assertStringStartsWith('req_', $id2);
        $this->assertNotEquals($id1, $id2);
        $this->assertEquals(28, strlen($id1)); // prefix (3) + underscore (1) + random (24)
    }

    public function test_generate_id_works_with_different_prefixes(): void
    {
        $reqId = $this->formatter->generateId('req');
        $fldId = $this->formatter->generateId('fld');
        $envId = $this->formatter->generateId('env');

        $this->assertStringStartsWith('req_', $reqId);
        $this->assertStringStartsWith('fld_', $fldId);
        $this->assertStringStartsWith('env_', $envId);
    }

    public function test_format_headers_converts_headers_to_insomnia_format(): void
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Custom-Header' => 'custom-value',
        ];

        $result = $this->formatter->formatHeaders($headers);

        $this->assertCount(3, $result);
        $this->assertEquals(['name' => 'Content-Type', 'value' => 'application/json'], $result[0]);
        $this->assertEquals(['name' => 'Accept', 'value' => 'application/json'], $result[1]);
        $this->assertEquals(['name' => 'X-Custom-Header', 'value' => 'custom-value'], $result[2]);
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
        $this->assertEquals('{{ _.bearer_token }}', $result['token']);
        $this->assertEquals('Bearer', $result['prefix']);
    }

    public function test_format_auth_returns_api_key_format(): void
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
        $this->assertEquals('{{ _.api_key }}', $result['key']);
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
                    ],
                ],
            ],
        ];

        $result = $this->formatter->formatAuth($security, $securitySchemes);

        $this->assertEquals('oauth2', $result['type']);
        $this->assertEquals('authorization_code', $result['grantType']);
        $this->assertEquals('{{ _.oauth2_token_url }}', $result['accessTokenUrl']);
        $this->assertEquals('{{ _.oauth2_auth_url }}', $result['authorizationUrl']);
        $this->assertEquals('{{ _.oauth2_client_id }}', $result['clientId']);
        $this->assertEquals('{{ _.oauth2_client_secret }}', $result['clientSecret']);
        $this->assertEquals('read write', $result['scope']);
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

    public function test_convert_path_converts_openapi_path_to_insomnia_format(): void
    {
        $this->assertEquals('/users/{{ _.id }}', $this->formatter->convertPath('/users/{id}'));
        $this->assertEquals('/users/{{ _.userId }}/posts/{{ _.postId }}', $this->formatter->convertPath('/users/{userId}/posts/{postId}'));
        $this->assertEquals('/users', $this->formatter->convertPath('/users'));
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
        $result = $this->formatter->getContentType([]);
        $this->assertEquals('application/json', $result);
    }

    public function test_generate_environment_data_with_servers(): void
    {
        $servers = [
            ['url' => 'https://api.example.com/v1'],
            ['url' => 'http://localhost:8080/api'],
        ];

        $result = $this->formatter->generateEnvironmentData($servers);

        $this->assertEquals('{{ _.scheme }}://{{ _.host }}{{ _.base_path }}', $result['base_url']);
        $this->assertEquals('https', $result['scheme']);
        $this->assertEquals('api.example.com', $result['host']);
        $this->assertEquals('/v1', $result['base_path']);
    }

    public function test_generate_environment_data_with_empty_servers(): void
    {
        $result = $this->formatter->generateEnvironmentData([]);

        $this->assertEquals('{{ _.scheme }}://{{ _.host }}{{ _.base_path }}', $result['base_url']);
        $this->assertEquals('http', $result['scheme']);
        $this->assertEquals('localhost', $result['host']);
        $this->assertEquals('/api', $result['base_path']);
    }

    public function test_generate_environment_data_handles_url_without_path(): void
    {
        $servers = [
            ['url' => 'https://api.example.com'],
        ];

        $result = $this->formatter->generateEnvironmentData($servers);

        $this->assertEquals('https', $result['scheme']);
        $this->assertEquals('api.example.com', $result['host']);
    }

    public function test_format_query_parameters_converts_to_insomnia_format(): void
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

        $this->assertStringStartsWith('pair_', $result[0]['id']);
        $this->assertEquals('page', $result[0]['name']);
        $this->assertEquals('1', $result[0]['value']);
        $this->assertEquals('Page number', $result[0]['description']);
        $this->assertTrue($result[0]['disabled']);

        $this->assertEquals('limit', $result[1]['name']);
        $this->assertEquals('10', $result[1]['value']);
        $this->assertFalse($result[1]['disabled']);
    }

    public function test_format_query_parameters_handles_empty_array(): void
    {
        $result = $this->formatter->formatQueryParameters([]);
        $this->assertEquals([], $result);
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

    public function test_format_form_data_parameters(): void
    {
        $exampleFormatter = new RequestExampleFormatter;
        $properties = [
            'name' => ['type' => 'string', 'description' => 'User name'],
            'email' => ['type' => 'string', 'format' => 'email', 'description' => 'User email'],
            'avatar' => ['type' => 'string', 'format' => 'binary', 'description' => 'Profile image'],
        ];

        $result = $this->formatter->formatFormDataParameters($properties, $exampleFormatter);

        $this->assertCount(3, $result);

        // Regular fields
        $this->assertStringStartsWith('pair_', $result[0]['id']);
        $this->assertEquals('name', $result[0]['name']);
        $this->assertIsString($result[0]['value']);
        $this->assertEquals('User name', $result[0]['description']);

        // File field
        $this->assertEquals('avatar', $result[2]['name']);
        $this->assertEquals('', $result[2]['value']);
        $this->assertEquals('file', $result[2]['type']);
        $this->assertEquals('', $result[2]['fileName']);
    }

    public function test_format_form_data_parameters_handles_empty_properties(): void
    {
        $exampleFormatter = new RequestExampleFormatter;
        $result = $this->formatter->formatFormDataParameters([], $exampleFormatter);
        $this->assertEquals([], $result);
    }

    public function test_detect_auth_type_detects_bearer(): void
    {
        $this->assertEquals('bearer', $this->formatter->detectAuthType('bearerAuth'));
        $this->assertEquals('bearer', $this->formatter->detectAuthType('Bearer'));
        $this->assertEquals('bearer', $this->formatter->detectAuthType('jwt_bearer_token'));
    }

    public function test_detect_auth_type_detects_api_key(): void
    {
        $this->assertEquals('apikey', $this->formatter->detectAuthType('apiKey'));
        $this->assertEquals('apikey', $this->formatter->detectAuthType('api_key'));
        $this->assertEquals('apikey', $this->formatter->detectAuthType('ApiKeyAuth'));
    }

    public function test_detect_auth_type_detects_oauth2(): void
    {
        $this->assertEquals('oauth2', $this->formatter->detectAuthType('oauth2'));
        $this->assertEquals('oauth2', $this->formatter->detectAuthType('OAuth'));
        $this->assertEquals('oauth2', $this->formatter->detectAuthType('oauth2_auth'));
    }

    public function test_detect_auth_type_detects_basic(): void
    {
        $this->assertEquals('basic', $this->formatter->detectAuthType('basic'));
        $this->assertEquals('basic', $this->formatter->detectAuthType('basicAuth'));
        $this->assertEquals('basic', $this->formatter->detectAuthType('Basic'));
    }

    public function test_detect_auth_type_returns_none_for_unknown(): void
    {
        $this->assertEquals('none', $this->formatter->detectAuthType('unknown'));
        $this->assertEquals('none', $this->formatter->detectAuthType('customAuth'));
        $this->assertEquals('none', $this->formatter->detectAuthType(''));
    }
}
