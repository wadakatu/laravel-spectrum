<?php

namespace LaravelPrism\Tests\Unit\Generators;

use LaravelPrism\Generators\SecuritySchemeGenerator;
use LaravelPrism\Tests\TestCase;

class SecuritySchemeGeneratorTest extends TestCase
{
    private SecuritySchemeGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new SecuritySchemeGenerator;
    }

    /** @test */
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

    /** @test */
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

    /** @test */
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

    /** @test */
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

    /** @test */
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

    /** @test */
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
}
