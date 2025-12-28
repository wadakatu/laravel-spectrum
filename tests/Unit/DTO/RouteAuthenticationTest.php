<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\AuthenticationScheme;
use LaravelSpectrum\DTO\AuthenticationType;
use LaravelSpectrum\DTO\RouteAuthentication;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RouteAuthenticationTest extends TestCase
{
    #[Test]
    public function it_can_create_route_authentication(): void
    {
        $scheme = new AuthenticationScheme(
            type: AuthenticationType::HTTP,
            name: 'bearerAuth',
            scheme: 'bearer',
            bearerFormat: 'JWT',
            description: 'Bearer token authentication',
        );

        $routeAuth = new RouteAuthentication(
            scheme: $scheme,
            middleware: ['auth:sanctum'],
            required: true,
        );

        $this->assertSame($scheme, $routeAuth->scheme);
        $this->assertEquals(['auth:sanctum'], $routeAuth->middleware);
        $this->assertTrue($routeAuth->required);
    }

    #[Test]
    public function it_can_create_route_authentication_with_defaults(): void
    {
        $scheme = new AuthenticationScheme(
            type: AuthenticationType::HTTP,
            name: 'basicAuth',
            scheme: 'basic',
        );

        $routeAuth = new RouteAuthentication(
            scheme: $scheme,
            middleware: ['auth.basic'],
        );

        $this->assertSame($scheme, $routeAuth->scheme);
        $this->assertEquals(['auth.basic'], $routeAuth->middleware);
        $this->assertTrue($routeAuth->required); // Default is true
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $data = [
            'scheme' => [
                'type' => 'http',
                'name' => 'sanctumAuth',
                'scheme' => 'bearer',
                'bearerFormat' => 'JWT',
                'description' => 'Sanctum authentication',
            ],
            'middleware' => ['auth:sanctum', 'verified'],
            'required' => true,
        ];

        $routeAuth = RouteAuthentication::fromArray($data);

        $this->assertEquals('sanctumAuth', $routeAuth->scheme->name);
        $this->assertEquals('bearer', $routeAuth->scheme->scheme);
        $this->assertEquals(['auth:sanctum', 'verified'], $routeAuth->middleware);
        $this->assertTrue($routeAuth->required);
    }

    #[Test]
    public function it_creates_from_array_with_authentication_scheme_object(): void
    {
        $scheme = new AuthenticationScheme(
            type: AuthenticationType::HTTP,
            name: 'bearerAuth',
            scheme: 'bearer',
        );

        $data = [
            'scheme' => $scheme,
            'middleware' => ['auth'],
            'required' => false,
        ];

        $routeAuth = RouteAuthentication::fromArray($data);

        $this->assertSame($scheme, $routeAuth->scheme);
        $this->assertEquals(['auth'], $routeAuth->middleware);
        $this->assertFalse($routeAuth->required);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $data = [
            'scheme' => [
                'type' => 'http',
                'name' => 'basicAuth',
                'scheme' => 'basic',
            ],
            'middleware' => ['auth.basic'],
        ];

        $routeAuth = RouteAuthentication::fromArray($data);

        $this->assertTrue($routeAuth->required); // Default
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

        $routeAuth = new RouteAuthentication(
            scheme: $scheme,
            middleware: ['auth:sanctum'],
            required: true,
        );

        $array = $routeAuth->toArray();

        $this->assertArrayHasKey('scheme', $array);
        $this->assertArrayHasKey('middleware', $array);
        $this->assertArrayHasKey('required', $array);
        $this->assertEquals('bearerAuth', $array['scheme']['name']);
        $this->assertEquals(['auth:sanctum'], $array['middleware']);
        $this->assertTrue($array['required']);
    }

    #[Test]
    public function it_survives_serialization_round_trip(): void
    {
        $scheme = new AuthenticationScheme(
            type: AuthenticationType::HTTP,
            name: 'apiAuth',
            scheme: 'bearer',
            bearerFormat: 'API Token',
        );

        $original = new RouteAuthentication(
            scheme: $scheme,
            middleware: ['auth:api', 'throttle:60,1'],
            required: true,
        );

        $restored = RouteAuthentication::fromArray($original->toArray());

        $this->assertEquals($original->scheme->name, $restored->scheme->name);
        $this->assertEquals($original->scheme->scheme, $restored->scheme->scheme);
        $this->assertEquals($original->middleware, $restored->middleware);
        $this->assertEquals($original->required, $restored->required);
    }

    #[Test]
    public function it_has_is_required_helper(): void
    {
        $scheme = new AuthenticationScheme(
            type: AuthenticationType::HTTP,
            name: 'bearerAuth',
            scheme: 'bearer',
        );

        $required = new RouteAuthentication(
            scheme: $scheme,
            middleware: ['auth'],
            required: true,
        );

        $optional = new RouteAuthentication(
            scheme: $scheme,
            middleware: ['auth'],
            required: false,
        );

        $this->assertTrue($required->isRequired());
        $this->assertFalse($optional->isRequired());
    }

    #[Test]
    public function it_gets_scheme_name(): void
    {
        $scheme = new AuthenticationScheme(
            type: AuthenticationType::HTTP,
            name: 'sanctumAuth',
            scheme: 'bearer',
        );

        $routeAuth = new RouteAuthentication(
            scheme: $scheme,
            middleware: ['auth:sanctum'],
        );

        $this->assertEquals('sanctumAuth', $routeAuth->getSchemeName());
    }

    #[Test]
    public function it_checks_if_has_middleware(): void
    {
        $scheme = new AuthenticationScheme(
            type: AuthenticationType::HTTP,
            name: 'bearerAuth',
            scheme: 'bearer',
        );

        $routeAuth = new RouteAuthentication(
            scheme: $scheme,
            middleware: ['auth:sanctum', 'verified', 'throttle:60,1'],
        );

        $this->assertTrue($routeAuth->hasMiddleware('auth:sanctum'));
        $this->assertTrue($routeAuth->hasMiddleware('verified'));
        $this->assertFalse($routeAuth->hasMiddleware('admin'));
    }
}
