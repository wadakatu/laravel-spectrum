<?php

namespace LaravelSpectrum\Tests\Unit\Analyzers;

use LaravelSpectrum\Analyzers\AuthenticationAnalyzer;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AuthenticationAnalyzerTest extends TestCase
{
    private AuthenticationAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new AuthenticationAnalyzer;
    }

    #[Test]
    public function it_analyzes_routes_with_authentication()
    {
        $routes = [
            [
                'uri' => 'api/users',
                'middleware' => ['auth:sanctum'],
            ],
            [
                'uri' => 'api/public',
                'middleware' => [],
            ],
            [
                'uri' => 'api/admin',
                'middleware' => ['auth:sanctum', 'role:admin'],
            ],
        ];

        $result = $this->analyzer->analyze($routes);

        $this->assertArrayHasKey('schemes', $result);
        $this->assertArrayHasKey('routes', $result);

        // Sanctum認証スキームが検出される
        $this->assertArrayHasKey('sanctumAuth', $result['schemes']);

        // ルート0と2に認証情報がある
        $this->assertArrayHasKey(0, $result['routes']);
        $this->assertArrayHasKey(2, $result['routes']);
        $this->assertArrayNotHasKey(1, $result['routes']); // publicルートには認証なし
    }

    #[Test]
    public function it_analyzes_single_route_authentication()
    {
        $route = [
            'uri' => 'api/profile',
            'middleware' => ['auth:api', 'verified'],
        ];

        $auth = $this->analyzer->analyzeRoute($route);

        $this->assertNotNull($auth);
        $this->assertEquals('apiAuth', $auth['scheme']['name']);
        $this->assertTrue($auth['required']);
    }

    #[Test]
    public function it_returns_null_for_routes_without_authentication()
    {
        $route = [
            'uri' => 'api/health',
            'middleware' => ['throttle:10,1'],
        ];

        $auth = $this->analyzer->analyzeRoute($route);

        $this->assertNull($auth);
    }

    #[Test]
    public function it_loads_custom_schemes_from_config()
    {
        config(['spectrum.authentication.custom_schemes' => [
            'jwt-auth' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'JWT',
                'description' => 'Custom JWT',
                'name' => 'jwtAuth',
            ],
        ]]);

        $this->analyzer->loadCustomSchemes();

        $route = [
            'uri' => 'api/test',
            'middleware' => ['jwt-auth'],
        ];

        $auth = $this->analyzer->analyzeRoute($route);

        $this->assertNotNull($auth);
        $this->assertEquals('jwtAuth', $auth['scheme']['name']);
    }

    #[Test]
    public function it_gets_global_authentication_when_enabled()
    {
        config(['spectrum.authentication.global' => [
            'enabled' => true,
            'scheme' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'JWT',
                'name' => 'globalAuth',
            ],
            'required' => true,
        ]]);

        $globalAuth = $this->analyzer->getGlobalAuthentication();

        $this->assertNotNull($globalAuth);
        $this->assertEquals('globalAuth', $globalAuth['scheme']['name']);
        $this->assertTrue($globalAuth['required']);
    }

    #[Test]
    public function it_returns_null_when_global_authentication_is_disabled()
    {
        config(['spectrum.authentication.global' => [
            'enabled' => false,
        ]]);

        $globalAuth = $this->analyzer->getGlobalAuthentication();

        $this->assertNull($globalAuth);
    }
}
