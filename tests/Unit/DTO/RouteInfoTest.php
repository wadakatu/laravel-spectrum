<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\RouteInfo;
use LaravelSpectrum\DTO\RouteParameterInfo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RouteInfoTest extends TestCase
{
    #[Test]
    public function it_can_be_constructed(): void
    {
        $info = new RouteInfo(
            uri: 'api/users/{id}',
            httpMethods: ['GET'],
            controller: 'App\\Http\\Controllers\\UserController',
            method: 'show',
            name: 'users.show',
            middleware: ['auth:api'],
            parameters: [
                new RouteParameterInfo(name: 'id', required: true),
            ],
        );

        $this->assertEquals('api/users/{id}', $info->uri);
        $this->assertEquals(['GET'], $info->httpMethods);
        $this->assertEquals('App\\Http\\Controllers\\UserController', $info->controller);
        $this->assertEquals('show', $info->method);
        $this->assertEquals('users.show', $info->name);
        $this->assertEquals(['auth:api'], $info->middleware);
        $this->assertCount(1, $info->parameters);
        $this->assertInstanceOf(RouteParameterInfo::class, $info->parameters[0]);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $array = [
            'uri' => 'api/posts',
            'httpMethods' => ['GET', 'HEAD'],
            'controller' => 'PostController',
            'method' => 'index',
            'name' => 'posts.index',
            'middleware' => ['throttle:api'],
            'parameters' => [
                ['name' => 'page', 'required' => false, 'in' => 'query'],
            ],
        ];

        $info = RouteInfo::fromArray($array);

        $this->assertEquals('api/posts', $info->uri);
        $this->assertEquals(['GET', 'HEAD'], $info->httpMethods);
        $this->assertEquals('PostController', $info->controller);
        $this->assertEquals('index', $info->method);
        $this->assertEquals('posts.index', $info->name);
        $this->assertEquals(['throttle:api'], $info->middleware);
        $this->assertCount(1, $info->parameters);
        $this->assertInstanceOf(RouteParameterInfo::class, $info->parameters[0]);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $array = [
            'uri' => 'api/health',
            'httpMethods' => ['GET'],
        ];

        $info = RouteInfo::fromArray($array);

        $this->assertEquals('api/health', $info->uri);
        $this->assertEquals(['GET'], $info->httpMethods);
        $this->assertNull($info->controller);
        $this->assertNull($info->method);
        $this->assertNull($info->name);
        $this->assertEquals([], $info->middleware);
        $this->assertEquals([], $info->parameters);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $info = new RouteInfo(
            uri: 'api/users/{id}',
            httpMethods: ['PUT', 'PATCH'],
            controller: 'UserController',
            method: 'update',
            name: 'users.update',
            middleware: ['auth'],
            parameters: [
                new RouteParameterInfo(name: 'id', required: true),
            ],
        );

        $array = $info->toArray();

        $this->assertEquals('api/users/{id}', $array['uri']);
        $this->assertEquals(['PUT', 'PATCH'], $array['httpMethods']);
        $this->assertEquals('UserController', $array['controller']);
        $this->assertEquals('update', $array['method']);
        $this->assertEquals('users.update', $array['name']);
        $this->assertEquals(['auth'], $array['middleware']);
        $this->assertCount(1, $array['parameters']);
        $this->assertIsArray($array['parameters'][0]);
    }

    #[Test]
    public function it_checks_controller_availability(): void
    {
        $withController = new RouteInfo(
            uri: 'api/users',
            httpMethods: ['GET'],
            controller: 'UserController',
            method: 'index',
        );
        $withoutController = new RouteInfo(
            uri: 'api/health',
            httpMethods: ['GET'],
        );

        $this->assertTrue($withController->hasController());
        $this->assertFalse($withoutController->hasController());
    }

    #[Test]
    public function it_gets_full_action(): void
    {
        $withAction = new RouteInfo(
            uri: 'api/users',
            httpMethods: ['GET'],
            controller: 'App\\Http\\Controllers\\UserController',
            method: 'index',
        );
        $withoutController = new RouteInfo(
            uri: 'api/health',
            httpMethods: ['GET'],
        );

        $this->assertEquals('App\\Http\\Controllers\\UserController@index', $withAction->getFullAction());
        $this->assertNull($withoutController->getFullAction());
    }

    #[Test]
    public function it_checks_middleware(): void
    {
        $info = new RouteInfo(
            uri: 'api/users',
            httpMethods: ['GET'],
            middleware: ['auth:api', 'throttle:60,1', 'verified'],
        );

        $this->assertTrue($info->hasMiddleware('auth:api'));
        $this->assertTrue($info->hasMiddleware('throttle:60,1'));
        $this->assertTrue($info->hasMiddleware('verified'));
        $this->assertFalse($info->hasMiddleware('guest'));
        $this->assertFalse($info->hasMiddleware('auth')); // exact match required
    }

    #[Test]
    public function it_checks_middleware_with_prefix(): void
    {
        $info = new RouteInfo(
            uri: 'api/users',
            httpMethods: ['GET'],
            middleware: ['auth:api', 'throttle:60,1'],
        );

        $this->assertTrue($info->hasMiddlewareStartingWith('auth'));
        $this->assertTrue($info->hasMiddlewareStartingWith('throttle'));
        $this->assertFalse($info->hasMiddlewareStartingWith('guest'));
    }

    #[Test]
    public function it_checks_route_name(): void
    {
        $named = new RouteInfo(uri: 'api/users', httpMethods: ['GET'], name: 'users.index');
        $unnamed = new RouteInfo(uri: 'api/users', httpMethods: ['GET']);

        $this->assertTrue($named->hasName());
        $this->assertFalse($unnamed->hasName());
    }

    #[Test]
    public function it_checks_parameters(): void
    {
        $withParams = new RouteInfo(
            uri: 'api/users/{id}',
            httpMethods: ['GET'],
            parameters: [new RouteParameterInfo(name: 'id')],
        );
        $withoutParams = new RouteInfo(uri: 'api/users', httpMethods: ['GET']);

        $this->assertTrue($withParams->hasParameters());
        $this->assertFalse($withoutParams->hasParameters());
    }

    #[Test]
    public function it_gets_primary_http_method(): void
    {
        $single = new RouteInfo(uri: 'api/users', httpMethods: ['POST']);
        $multiple = new RouteInfo(uri: 'api/users/{id}', httpMethods: ['PUT', 'PATCH']);

        $this->assertEquals('POST', $single->getPrimaryMethod());
        $this->assertEquals('PUT', $multiple->getPrimaryMethod());
    }

    #[Test]
    public function it_checks_if_single_method(): void
    {
        $single = new RouteInfo(uri: 'api/users', httpMethods: ['GET']);
        $multiple = new RouteInfo(uri: 'api/users/{id}', httpMethods: ['PUT', 'PATCH']);

        $this->assertTrue($single->isSingleMethod());
        $this->assertFalse($multiple->isSingleMethod());
    }

    #[Test]
    public function it_generates_route_identifier(): void
    {
        $info = new RouteInfo(uri: 'api/users/{id}', httpMethods: ['GET', 'HEAD']);

        $this->assertEquals('route:GET:HEAD:api/users/{id}', $info->getRouteId());
    }

    #[Test]
    public function it_converts_to_openapi_path(): void
    {
        $info = new RouteInfo(uri: 'api/users/{user}/posts/{post}', httpMethods: ['GET']);

        $this->assertEquals('/api/users/{user}/posts/{post}', $info->toOpenApiPath());
    }

    #[Test]
    public function it_converts_uri_without_leading_slash(): void
    {
        $withSlash = new RouteInfo(uri: '/api/users', httpMethods: ['GET']);
        $withoutSlash = new RouteInfo(uri: 'api/users', httpMethods: ['GET']);

        $this->assertEquals('/api/users', $withSlash->toOpenApiPath());
        $this->assertEquals('/api/users', $withoutSlash->toOpenApiPath());
    }
}
