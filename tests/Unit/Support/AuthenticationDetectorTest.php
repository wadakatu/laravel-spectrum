<?php

namespace LaravelSpectrum\Tests\Unit\Support;

use LaravelSpectrum\Support\AuthenticationDetector;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AuthenticationDetectorTest extends TestCase
{
    #[Test]
    public function it_detects_sanctum_authentication()
    {
        $middleware = ['auth:sanctum', 'throttle:api'];
        $scheme = AuthenticationDetector::detectFromMiddleware($middleware);

        $this->assertNotNull($scheme);
        $this->assertEquals('http', $scheme['type']);
        $this->assertEquals('bearer', $scheme['scheme']);
        $this->assertEquals('JWT', $scheme['bearerFormat']);
        $this->assertEquals('sanctumAuth', $scheme['name']);
    }

    #[Test]
    public function it_detects_passport_authentication()
    {
        $middleware = ['passport', 'scope:read'];
        $scheme = AuthenticationDetector::detectFromMiddleware($middleware);

        $this->assertNotNull($scheme);
        $this->assertEquals('oauth2', $scheme['type']);
        $this->assertArrayHasKey('flows', $scheme);
        $this->assertEquals('passportAuth', $scheme['name']);
    }

    #[Test]
    public function it_detects_basic_authentication()
    {
        $middleware = ['auth.basic'];
        $scheme = AuthenticationDetector::detectFromMiddleware($middleware);

        $this->assertNotNull($scheme);
        $this->assertEquals('http', $scheme['type']);
        $this->assertEquals('basic', $scheme['scheme']);
        $this->assertEquals('basicAuth', $scheme['name']);
    }

    #[Test]
    public function it_detects_api_key_authentication()
    {
        $middleware = ['check-api-key'];
        $scheme = AuthenticationDetector::detectFromMiddleware($middleware);

        $this->assertNotNull($scheme);
        $this->assertEquals('apiKey', $scheme['type']);
        $this->assertEquals('header', $scheme['in']);
        $this->assertEquals('apiKeyAuth', $scheme['name']);
    }

    #[Test]
    public function it_returns_null_for_non_auth_middleware()
    {
        $middleware = ['throttle:60,1', 'cors'];
        $scheme = AuthenticationDetector::detectFromMiddleware($middleware);

        $this->assertNull($scheme);
    }

    #[Test]
    public function it_detects_custom_guard_authentication()
    {
        $middleware = ['auth:admin'];
        $scheme = AuthenticationDetector::detectFromMiddleware($middleware);

        $this->assertNotNull($scheme);
        $this->assertEquals('http', $scheme['type']);
        $this->assertEquals('bearer', $scheme['scheme']);
        $this->assertEquals('adminAuth', $scheme['name']);
    }

    #[Test]
    public function it_can_add_custom_authentication_schemes()
    {
        AuthenticationDetector::addCustomScheme('my-custom-auth', [
            'type' => 'apiKey',
            'in' => 'query',
            'name' => 'token',
            'description' => 'Custom token auth',
        ]);

        $middleware = ['my-custom-auth'];
        $scheme = AuthenticationDetector::detectFromMiddleware($middleware);

        $this->assertNotNull($scheme);
        $this->assertEquals('apiKey', $scheme['type']);
        $this->assertEquals('query', $scheme['in']);
    }

    #[Test]
    public function it_detects_multiple_authentication_schemes()
    {
        $middleware = ['auth:sanctum', 'auth.basic', 'throttle'];
        $schemes = AuthenticationDetector::detectMultipleSchemes($middleware);

        $this->assertCount(2, $schemes);
        $this->assertEquals('sanctumAuth', $schemes[0]['name']);
        $this->assertEquals('basicAuth', $schemes[1]['name']);
    }
}
