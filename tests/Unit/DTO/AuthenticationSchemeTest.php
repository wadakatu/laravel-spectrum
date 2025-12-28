<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\AuthenticationScheme;
use LaravelSpectrum\DTO\AuthenticationType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AuthenticationSchemeTest extends TestCase
{
    #[Test]
    public function it_can_be_constructed_for_bearer_auth(): void
    {
        $scheme = new AuthenticationScheme(
            type: AuthenticationType::HTTP,
            name: 'bearerAuth',
            scheme: 'bearer',
            bearerFormat: 'JWT',
            description: 'Bearer token authentication',
        );

        $this->assertEquals(AuthenticationType::HTTP, $scheme->type);
        $this->assertEquals('bearerAuth', $scheme->name);
        $this->assertEquals('bearer', $scheme->scheme);
        $this->assertEquals('JWT', $scheme->bearerFormat);
        $this->assertEquals('Bearer token authentication', $scheme->description);
    }

    #[Test]
    public function it_can_be_constructed_for_basic_auth(): void
    {
        $scheme = new AuthenticationScheme(
            type: AuthenticationType::HTTP,
            name: 'basicAuth',
            scheme: 'basic',
            description: 'Basic HTTP authentication',
        );

        $this->assertEquals('basic', $scheme->scheme);
        $this->assertNull($scheme->bearerFormat);
    }

    #[Test]
    public function it_can_be_constructed_for_api_key(): void
    {
        $scheme = new AuthenticationScheme(
            type: AuthenticationType::API_KEY,
            name: 'apiKeyAuth',
            in: 'header',
            headerName: 'X-API-Key',
            description: 'API Key authentication',
        );

        $this->assertEquals(AuthenticationType::API_KEY, $scheme->type);
        $this->assertEquals('header', $scheme->in);
        $this->assertEquals('X-API-Key', $scheme->headerName);
    }

    #[Test]
    public function it_can_be_constructed_for_oauth2(): void
    {
        $flows = [
            'authorizationCode' => [
                'authorizationUrl' => '/oauth/authorize',
                'tokenUrl' => '/oauth/token',
                'scopes' => [],
            ],
        ];

        $scheme = new AuthenticationScheme(
            type: AuthenticationType::OAUTH2,
            name: 'oauth2Auth',
            flows: $flows,
            description: 'OAuth2 authentication',
        );

        $this->assertEquals(AuthenticationType::OAUTH2, $scheme->type);
        $this->assertEquals($flows, $scheme->flows);
    }

    #[Test]
    public function it_can_be_constructed_for_openid_connect(): void
    {
        $scheme = new AuthenticationScheme(
            type: AuthenticationType::OPENID_CONNECT,
            name: 'oidcAuth',
            openIdConnectUrl: 'https://example.com/.well-known/openid-configuration',
            description: 'OpenID Connect authentication',
        );

        $this->assertEquals(AuthenticationType::OPENID_CONNECT, $scheme->type);
        $this->assertEquals('https://example.com/.well-known/openid-configuration', $scheme->openIdConnectUrl);
        $this->assertEquals('OpenID Connect authentication', $scheme->description);
    }

    #[Test]
    public function it_creates_from_array_for_http_type(): void
    {
        $data = [
            'type' => 'http',
            'name' => 'sanctumAuth',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT',
            'description' => 'Laravel Sanctum authentication',
        ];

        $scheme = AuthenticationScheme::fromArray($data);

        $this->assertEquals(AuthenticationType::HTTP, $scheme->type);
        $this->assertEquals('sanctumAuth', $scheme->name);
        $this->assertEquals('bearer', $scheme->scheme);
        $this->assertEquals('JWT', $scheme->bearerFormat);
    }

    #[Test]
    public function it_creates_from_array_for_api_key_type(): void
    {
        $data = [
            'type' => 'apiKey',
            'name' => 'apiKeyAuth',
            'in' => 'header',
            'headerName' => 'X-API-Key',
        ];

        $scheme = AuthenticationScheme::fromArray($data);

        $this->assertEquals(AuthenticationType::API_KEY, $scheme->type);
        $this->assertEquals('header', $scheme->in);
        $this->assertEquals('X-API-Key', $scheme->headerName);
    }

    #[Test]
    public function it_creates_from_array_for_api_key_using_name_as_header_name_fallback(): void
    {
        // When headerName is not provided, name should be used as the OpenAPI 'name' field
        $data = [
            'type' => 'apiKey',
            'name' => 'X-API-Key',
            'in' => 'header',
        ];

        $scheme = AuthenticationScheme::fromArray($data);

        $this->assertEquals(AuthenticationType::API_KEY, $scheme->type);
        $this->assertEquals('X-API-Key', $scheme->name);
        $this->assertEquals('X-API-Key', $scheme->headerName);
        $this->assertEquals('header', $scheme->in);
    }

    #[Test]
    public function it_creates_from_array_for_oauth2_type(): void
    {
        $flows = [
            'password' => [
                'tokenUrl' => '/oauth/token',
                'scopes' => [],
            ],
        ];

        $data = [
            'type' => 'oauth2',
            'name' => 'passportAuth',
            'flows' => $flows,
        ];

        $scheme = AuthenticationScheme::fromArray($data);

        $this->assertEquals(AuthenticationType::OAUTH2, $scheme->type);
        $this->assertEquals($flows, $scheme->flows);
    }

    #[Test]
    public function it_creates_from_array_for_openid_connect_type(): void
    {
        $data = [
            'type' => 'openIdConnect',
            'name' => 'oidcAuth',
            'openIdConnectUrl' => 'https://example.com/.well-known/openid-configuration',
            'description' => 'OpenID Connect',
        ];

        $scheme = AuthenticationScheme::fromArray($data);

        $this->assertEquals(AuthenticationType::OPENID_CONNECT, $scheme->type);
        $this->assertEquals('https://example.com/.well-known/openid-configuration', $scheme->openIdConnectUrl);
        $this->assertEquals('OpenID Connect', $scheme->description);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $scheme = new AuthenticationScheme(
            type: AuthenticationType::HTTP,
            name: 'bearerAuth',
            scheme: 'bearer',
            bearerFormat: 'JWT',
            description: 'Bearer authentication',
        );

        $array = $scheme->toArray();

        $this->assertEquals([
            'type' => 'http',
            'name' => 'bearerAuth',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT',
            'description' => 'Bearer authentication',
        ], $array);
    }

    #[Test]
    public function it_converts_to_array_without_null_values(): void
    {
        $scheme = new AuthenticationScheme(
            type: AuthenticationType::HTTP,
            name: 'basicAuth',
            scheme: 'basic',
        );

        $array = $scheme->toArray();

        $this->assertArrayNotHasKey('bearerFormat', $array);
        $this->assertArrayNotHasKey('in', $array);
        $this->assertArrayNotHasKey('headerName', $array);
        $this->assertArrayNotHasKey('flows', $array);
        $this->assertArrayNotHasKey('description', $array);
    }

    #[Test]
    public function it_converts_to_openapi_security_scheme_for_bearer(): void
    {
        $scheme = new AuthenticationScheme(
            type: AuthenticationType::HTTP,
            name: 'bearerAuth',
            scheme: 'bearer',
            bearerFormat: 'JWT',
            description: 'JWT authentication',
        );

        $openApi = $scheme->toOpenApiSecurityScheme();

        $this->assertEquals([
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT',
            'description' => 'JWT authentication',
        ], $openApi);
    }

    #[Test]
    public function it_converts_to_openapi_security_scheme_for_basic(): void
    {
        $scheme = new AuthenticationScheme(
            type: AuthenticationType::HTTP,
            name: 'basicAuth',
            scheme: 'basic',
        );

        $openApi = $scheme->toOpenApiSecurityScheme();

        $this->assertEquals([
            'type' => 'http',
            'scheme' => 'basic',
        ], $openApi);
    }

    #[Test]
    public function it_converts_to_openapi_security_scheme_for_api_key(): void
    {
        $scheme = new AuthenticationScheme(
            type: AuthenticationType::API_KEY,
            name: 'apiKeyAuth',
            in: 'header',
            headerName: 'X-API-Key',
        );

        $openApi = $scheme->toOpenApiSecurityScheme();

        $this->assertEquals([
            'type' => 'apiKey',
            'in' => 'header',
            'name' => 'X-API-Key',
        ], $openApi);
    }

    #[Test]
    public function it_converts_to_openapi_security_scheme_for_oauth2(): void
    {
        $flows = [
            'authorizationCode' => [
                'authorizationUrl' => '/oauth/authorize',
                'tokenUrl' => '/oauth/token',
                'scopes' => [],
            ],
        ];

        $scheme = new AuthenticationScheme(
            type: AuthenticationType::OAUTH2,
            name: 'oauth2Auth',
            flows: $flows,
        );

        $openApi = $scheme->toOpenApiSecurityScheme();

        $this->assertEquals([
            'type' => 'oauth2',
            'flows' => $flows,
        ], $openApi);
    }

    #[Test]
    public function it_converts_to_openapi_security_scheme_for_openid_connect(): void
    {
        $scheme = new AuthenticationScheme(
            type: AuthenticationType::OPENID_CONNECT,
            name: 'oidcAuth',
            openIdConnectUrl: 'https://example.com/.well-known/openid-configuration',
            description: 'OpenID Connect authentication',
        );

        $openApi = $scheme->toOpenApiSecurityScheme();

        $this->assertEquals([
            'type' => 'openIdConnect',
            'openIdConnectUrl' => 'https://example.com/.well-known/openid-configuration',
            'description' => 'OpenID Connect authentication',
        ], $openApi);
    }

    #[Test]
    public function it_checks_if_is_bearer(): void
    {
        $bearer = new AuthenticationScheme(
            type: AuthenticationType::HTTP,
            name: 'bearerAuth',
            scheme: 'bearer',
        );
        $basic = new AuthenticationScheme(
            type: AuthenticationType::HTTP,
            name: 'basicAuth',
            scheme: 'basic',
        );
        $apiKey = new AuthenticationScheme(
            type: AuthenticationType::API_KEY,
            name: 'apiKeyAuth',
            in: 'header',
        );

        $this->assertTrue($bearer->isBearer());
        $this->assertFalse($basic->isBearer());
        $this->assertFalse($apiKey->isBearer());
    }

    #[Test]
    public function it_checks_if_is_basic(): void
    {
        $basic = new AuthenticationScheme(
            type: AuthenticationType::HTTP,
            name: 'basicAuth',
            scheme: 'basic',
        );
        $bearer = new AuthenticationScheme(
            type: AuthenticationType::HTTP,
            name: 'bearerAuth',
            scheme: 'bearer',
        );

        $this->assertTrue($basic->isBasic());
        $this->assertFalse($bearer->isBasic());
    }

    #[Test]
    public function it_checks_if_is_api_key(): void
    {
        $apiKey = new AuthenticationScheme(
            type: AuthenticationType::API_KEY,
            name: 'apiKeyAuth',
            in: 'header',
        );
        $bearer = new AuthenticationScheme(
            type: AuthenticationType::HTTP,
            name: 'bearerAuth',
            scheme: 'bearer',
        );

        $this->assertTrue($apiKey->isApiKey());
        $this->assertFalse($bearer->isApiKey());
    }

    #[Test]
    public function it_checks_if_is_oauth2(): void
    {
        $oauth2 = new AuthenticationScheme(
            type: AuthenticationType::OAUTH2,
            name: 'oauth2Auth',
            flows: [],
        );
        $bearer = new AuthenticationScheme(
            type: AuthenticationType::HTTP,
            name: 'bearerAuth',
            scheme: 'bearer',
        );

        $this->assertTrue($oauth2->isOAuth2());
        $this->assertFalse($bearer->isOAuth2());
    }

    #[Test]
    public function it_checks_if_is_http(): void
    {
        $http = new AuthenticationScheme(
            type: AuthenticationType::HTTP,
            name: 'bearerAuth',
            scheme: 'bearer',
        );
        $apiKey = new AuthenticationScheme(
            type: AuthenticationType::API_KEY,
            name: 'apiKeyAuth',
            in: 'header',
        );

        $this->assertTrue($http->isHttp());
        $this->assertFalse($apiKey->isHttp());
    }

    #[Test]
    public function it_checks_if_is_openid_connect(): void
    {
        $oidc = new AuthenticationScheme(
            type: AuthenticationType::OPENID_CONNECT,
            name: 'oidcAuth',
            openIdConnectUrl: 'https://example.com/.well-known/openid-configuration',
        );
        $bearer = new AuthenticationScheme(
            type: AuthenticationType::HTTP,
            name: 'bearerAuth',
            scheme: 'bearer',
        );

        $this->assertTrue($oidc->isOpenIdConnect());
        $this->assertFalse($bearer->isOpenIdConnect());
    }

    #[Test]
    public function it_survives_serialization_round_trip(): void
    {
        $original = new AuthenticationScheme(
            type: AuthenticationType::HTTP,
            name: 'sanctumAuth',
            scheme: 'bearer',
            bearerFormat: 'JWT',
            description: 'Laravel Sanctum authentication',
        );

        $restored = AuthenticationScheme::fromArray($original->toArray());

        $this->assertEquals($original->type, $restored->type);
        $this->assertEquals($original->name, $restored->name);
        $this->assertEquals($original->scheme, $restored->scheme);
        $this->assertEquals($original->bearerFormat, $restored->bearerFormat);
        $this->assertEquals($original->description, $restored->description);
    }

    #[Test]
    public function it_survives_serialization_round_trip_for_openid_connect(): void
    {
        $original = new AuthenticationScheme(
            type: AuthenticationType::OPENID_CONNECT,
            name: 'oidcAuth',
            openIdConnectUrl: 'https://example.com/.well-known/openid-configuration',
            description: 'OpenID Connect authentication',
        );

        $restored = AuthenticationScheme::fromArray($original->toArray());

        $this->assertEquals($original->type, $restored->type);
        $this->assertEquals($original->name, $restored->name);
        $this->assertEquals($original->openIdConnectUrl, $restored->openIdConnectUrl);
        $this->assertEquals($original->description, $restored->description);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $data = [];

        $scheme = AuthenticationScheme::fromArray($data);

        $this->assertEquals(AuthenticationType::HTTP, $scheme->type);
        $this->assertEquals('', $scheme->name);
        $this->assertNull($scheme->scheme);
        $this->assertNull($scheme->bearerFormat);
        $this->assertNull($scheme->in);
        $this->assertNull($scheme->headerName);
        $this->assertNull($scheme->flows);
        $this->assertNull($scheme->openIdConnectUrl);
        $this->assertNull($scheme->description);
    }

    #[Test]
    public function it_creates_from_array_with_invalid_type_defaults_to_http(): void
    {
        $data = [
            'type' => 'invalid_type',
            'name' => 'testAuth',
        ];

        $scheme = AuthenticationScheme::fromArray($data);

        $this->assertEquals(AuthenticationType::HTTP, $scheme->type);
        $this->assertEquals('testAuth', $scheme->name);
    }

    #[Test]
    public function it_converts_to_array_with_openid_connect_url(): void
    {
        $scheme = new AuthenticationScheme(
            type: AuthenticationType::OPENID_CONNECT,
            name: 'oidcAuth',
            openIdConnectUrl: 'https://example.com/.well-known/openid-configuration',
        );

        $array = $scheme->toArray();

        $this->assertArrayHasKey('openIdConnectUrl', $array);
        $this->assertEquals('https://example.com/.well-known/openid-configuration', $array['openIdConnectUrl']);
    }
}
