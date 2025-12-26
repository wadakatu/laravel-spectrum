<?php

namespace LaravelSpectrum\Tests\Unit\Generators;

use LaravelSpectrum\Generators\SecuritySchemeGenerator;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class SecuritySchemeGeneratorTest extends TestCase
{
    private SecuritySchemeGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new SecuritySchemeGenerator;
    }

    #[Test]
    public function it_generates_bearer_token_security_scheme()
    {
        $authSchemes = [
            'bearerAuth' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'JWT',
                'description' => 'JWT authentication',
                'name' => 'bearerAuth',
            ],
        ];

        $securitySchemes = $this->generator->generateSecuritySchemes($authSchemes);

        $this->assertArrayHasKey('bearerAuth', $securitySchemes);
        $this->assertEquals('http', $securitySchemes['bearerAuth']['type']);
        $this->assertEquals('bearer', $securitySchemes['bearerAuth']['scheme']);
        $this->assertEquals('JWT', $securitySchemes['bearerAuth']['bearerFormat']);
    }

    #[Test]
    public function it_generates_api_key_security_scheme()
    {
        $authSchemes = [
            'apiKeyAuth' => [
                'type' => 'apiKey',
                'in' => 'header',
                'name' => 'X-API-Key',
                'description' => 'API Key authentication',
            ],
        ];

        $securitySchemes = $this->generator->generateSecuritySchemes($authSchemes);

        $this->assertArrayHasKey('apiKeyAuth', $securitySchemes);
        $this->assertEquals('apiKey', $securitySchemes['apiKeyAuth']['type']);
        $this->assertEquals('header', $securitySchemes['apiKeyAuth']['in']);
        $this->assertEquals('X-API-Key', $securitySchemes['apiKeyAuth']['name']);
    }

    #[Test]
    public function it_generates_oauth2_security_scheme()
    {
        $authSchemes = [
            'oauth2' => [
                'type' => 'oauth2',
                'flows' => [
                    'authorizationCode' => [
                        'authorizationUrl' => '/oauth/authorize',
                        'tokenUrl' => '/oauth/token',
                        'scopes' => [
                            'read' => 'Read access',
                            'write' => 'Write access',
                        ],
                    ],
                ],
                'description' => 'OAuth2 authentication',
                'name' => 'oauth2',
            ],
        ];

        $securitySchemes = $this->generator->generateSecuritySchemes($authSchemes);

        $this->assertArrayHasKey('oauth2', $securitySchemes);
        $this->assertEquals('oauth2', $securitySchemes['oauth2']['type']);
        $this->assertArrayHasKey('flows', $securitySchemes['oauth2']);
    }

    #[Test]
    public function it_generates_endpoint_security()
    {
        $authentication = [
            'scheme' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'name' => 'bearerAuth',
            ],
            'required' => true,
        ];

        $security = $this->generator->generateEndpointSecurity($authentication);

        $this->assertCount(1, $security);
        $this->assertArrayHasKey('bearerAuth', $security[0]);
        $this->assertEquals([], $security[0]['bearerAuth']);
    }

    #[Test]
    public function it_generates_oauth2_endpoint_security_with_scopes()
    {
        $authentication = [
            'scheme' => [
                'type' => 'oauth2',
                'name' => 'oauth2',
            ],
            'required' => true,
            'scopes' => ['read', 'write'],
        ];

        $security = $this->generator->generateEndpointSecurity($authentication);

        $this->assertCount(1, $security);
        $this->assertArrayHasKey('oauth2', $security[0]);
        $this->assertEquals(['read', 'write'], $security[0]['oauth2']);
    }

    #[Test]
    public function it_returns_empty_array_for_non_required_authentication()
    {
        $authentication = [
            'scheme' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'name' => 'bearerAuth',
            ],
            'required' => false,
        ];

        $security = $this->generator->generateEndpointSecurity($authentication);

        $this->assertEmpty($security);
    }

    #[Test]
    public function it_returns_empty_array_for_empty_authentication(): void
    {
        $security = $this->generator->generateEndpointSecurity([]);

        $this->assertEmpty($security);
    }

    #[Test]
    public function it_generates_api_key_with_header_name(): void
    {
        $authSchemes = [
            'apiKeyAuth' => [
                'type' => 'apiKey',
                'in' => 'header',
                'headerName' => 'X-Custom-API-Key',
                'name' => 'apiKeyAuth',
                'description' => 'API Key in header',
            ],
        ];

        $securitySchemes = $this->generator->generateSecuritySchemes($authSchemes);

        $this->assertArrayHasKey('apiKeyAuth', $securitySchemes);
        $this->assertEquals('X-Custom-API-Key', $securitySchemes['apiKeyAuth']['name']);
    }

    #[Test]
    public function it_generates_api_key_in_query(): void
    {
        $authSchemes = [
            'apiKeyAuth' => [
                'type' => 'apiKey',
                'in' => 'query',
                'name' => 'api_key',
                'description' => 'API Key in query string',
            ],
        ];

        $securitySchemes = $this->generator->generateSecuritySchemes($authSchemes);

        $this->assertEquals('query', $securitySchemes['apiKeyAuth']['in']);
        $this->assertEquals('api_key', $securitySchemes['apiKeyAuth']['name']);
    }

    #[Test]
    public function it_generates_multiple_security_schemes(): void
    {
        $authSchemes = [
            'bearerAuth' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'JWT',
                'name' => 'bearerAuth',
            ],
            'apiKeyAuth' => [
                'type' => 'apiKey',
                'in' => 'header',
                'name' => 'X-API-Key',
            ],
        ];

        $securitySchemes = $this->generator->generateSecuritySchemes($authSchemes);

        $this->assertCount(2, $securitySchemes);
        $this->assertArrayHasKey('bearerAuth', $securitySchemes);
        $this->assertArrayHasKey('apiKeyAuth', $securitySchemes);
    }

    #[Test]
    public function it_generates_multiple_auth_security(): void
    {
        $authentications = [
            [
                'scheme' => ['type' => 'http', 'scheme' => 'bearer', 'name' => 'bearerAuth'],
                'required' => true,
            ],
            [
                'scheme' => ['type' => 'apiKey', 'in' => 'header', 'name' => 'apiKeyAuth'],
                'required' => true,
            ],
        ];

        $security = $this->generator->generateMultipleAuthSecurity($authentications);

        $this->assertCount(2, $security);
        $this->assertArrayHasKey('bearerAuth', $security[0]);
        $this->assertArrayHasKey('apiKeyAuth', $security[1]);
    }

    #[Test]
    public function it_filters_non_required_in_multiple_auth(): void
    {
        $authentications = [
            [
                'scheme' => ['type' => 'http', 'scheme' => 'bearer', 'name' => 'bearerAuth'],
                'required' => true,
            ],
            [
                'scheme' => ['type' => 'apiKey', 'in' => 'header', 'name' => 'apiKeyAuth'],
                'required' => false,
            ],
        ];

        $security = $this->generator->generateMultipleAuthSecurity($authentications);

        $this->assertCount(1, $security);
        $this->assertArrayHasKey('bearerAuth', $security[0]);
    }

    #[Test]
    public function it_merges_authentications_with_local_priority(): void
    {
        $global = ['type' => 'http', 'scheme' => 'basic'];
        $local = ['type' => 'http', 'scheme' => 'bearer'];

        $result = $this->generator->mergeAuthentications($global, $local);

        $this->assertEquals($local, $result);
    }

    #[Test]
    public function it_uses_global_when_no_local(): void
    {
        $global = ['type' => 'http', 'scheme' => 'basic'];

        $result = $this->generator->mergeAuthentications($global, null);

        $this->assertEquals($global, $result);
    }

    #[Test]
    public function it_returns_null_when_no_authentication(): void
    {
        $result = $this->generator->mergeAuthentications(null, null);

        $this->assertNull($result);
    }

    #[Test]
    public function it_generates_http_basic_security_scheme(): void
    {
        $authSchemes = [
            'basicAuth' => [
                'type' => 'http',
                'scheme' => 'basic',
                'description' => 'HTTP Basic authentication',
                'name' => 'basicAuth',
            ],
        ];

        $securitySchemes = $this->generator->generateSecuritySchemes($authSchemes);

        $this->assertArrayHasKey('basicAuth', $securitySchemes);
        $this->assertEquals('http', $securitySchemes['basicAuth']['type']);
        $this->assertEquals('basic', $securitySchemes['basicAuth']['scheme']);
        $this->assertEquals('HTTP Basic authentication', $securitySchemes['basicAuth']['description']);
    }

    #[Test]
    public function it_generates_scheme_without_description(): void
    {
        $authSchemes = [
            'bearerAuth' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'name' => 'bearerAuth',
            ],
        ];

        $securitySchemes = $this->generator->generateSecuritySchemes($authSchemes);

        $this->assertArrayNotHasKey('description', $securitySchemes['bearerAuth']);
    }

    #[Test]
    public function it_generates_empty_security_schemes_for_empty_input(): void
    {
        $securitySchemes = $this->generator->generateSecuritySchemes([]);

        $this->assertEmpty($securitySchemes);
    }
}
