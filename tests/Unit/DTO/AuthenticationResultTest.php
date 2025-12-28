<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\AuthenticationResult;
use LaravelSpectrum\DTO\AuthenticationScheme;
use LaravelSpectrum\DTO\AuthenticationType;
use LaravelSpectrum\DTO\RouteAuthentication;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AuthenticationResultTest extends TestCase
{
    private AuthenticationScheme $sanctumScheme;

    private AuthenticationScheme $basicScheme;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sanctumScheme = new AuthenticationScheme(
            type: AuthenticationType::HTTP,
            name: 'sanctumAuth',
            scheme: 'bearer',
            bearerFormat: 'JWT',
            description: 'Laravel Sanctum authentication',
        );

        $this->basicScheme = new AuthenticationScheme(
            type: AuthenticationType::HTTP,
            name: 'basicAuth',
            scheme: 'basic',
            description: 'Basic HTTP authentication',
        );
    }

    #[Test]
    public function it_can_create_authentication_result(): void
    {
        $schemes = [
            'sanctumAuth' => $this->sanctumScheme,
        ];

        $route0Auth = new RouteAuthentication(
            scheme: $this->sanctumScheme,
            middleware: ['auth:sanctum'],
            required: true,
        );

        $routes = [
            0 => $route0Auth,
        ];

        $result = new AuthenticationResult(
            schemes: $schemes,
            routes: $routes,
        );

        $this->assertCount(1, $result->schemes);
        $this->assertCount(1, $result->routes);
        $this->assertSame($this->sanctumScheme, $result->schemes['sanctumAuth']);
        $this->assertSame($route0Auth, $result->routes[0]);
    }

    #[Test]
    public function it_can_create_empty_result(): void
    {
        $result = AuthenticationResult::empty();

        $this->assertEmpty($result->schemes);
        $this->assertEmpty($result->routes);
        $this->assertTrue($result->isEmpty());
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $data = [
            'schemes' => [
                'sanctumAuth' => [
                    'type' => 'http',
                    'name' => 'sanctumAuth',
                    'scheme' => 'bearer',
                    'bearerFormat' => 'JWT',
                ],
            ],
            'routes' => [
                0 => [
                    'scheme' => [
                        'type' => 'http',
                        'name' => 'sanctumAuth',
                        'scheme' => 'bearer',
                    ],
                    'middleware' => ['auth:sanctum'],
                    'required' => true,
                ],
            ],
        ];

        $result = AuthenticationResult::fromArray($data);

        $this->assertCount(1, $result->schemes);
        $this->assertEquals('sanctumAuth', $result->schemes['sanctumAuth']->name);
        $this->assertCount(1, $result->routes);
        $this->assertTrue($result->routes[0]->required);
    }

    #[Test]
    public function it_creates_from_array_with_dto_objects(): void
    {
        $data = [
            'schemes' => [
                'sanctumAuth' => $this->sanctumScheme,
            ],
            'routes' => [
                0 => new RouteAuthentication(
                    scheme: $this->sanctumScheme,
                    middleware: ['auth:sanctum'],
                ),
            ],
        ];

        $result = AuthenticationResult::fromArray($data);

        $this->assertSame($this->sanctumScheme, $result->schemes['sanctumAuth']);
        $this->assertInstanceOf(RouteAuthentication::class, $result->routes[0]);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $result = AuthenticationResult::fromArray([]);

        $this->assertEmpty($result->schemes);
        $this->assertEmpty($result->routes);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $route0Auth = new RouteAuthentication(
            scheme: $this->sanctumScheme,
            middleware: ['auth:sanctum'],
            required: true,
        );

        $result = new AuthenticationResult(
            schemes: ['sanctumAuth' => $this->sanctumScheme],
            routes: [0 => $route0Auth],
        );

        $array = $result->toArray();

        $this->assertArrayHasKey('schemes', $array);
        $this->assertArrayHasKey('routes', $array);
        $this->assertArrayHasKey('sanctumAuth', $array['schemes']);
        $this->assertEquals('sanctumAuth', $array['schemes']['sanctumAuth']['name']);
        $this->assertArrayHasKey(0, $array['routes']);
        $this->assertTrue($array['routes'][0]['required']);
    }

    #[Test]
    public function it_survives_serialization_round_trip(): void
    {
        $route0Auth = new RouteAuthentication(
            scheme: $this->sanctumScheme,
            middleware: ['auth:sanctum'],
            required: true,
        );

        $original = new AuthenticationResult(
            schemes: ['sanctumAuth' => $this->sanctumScheme],
            routes: [0 => $route0Auth],
        );

        $restored = AuthenticationResult::fromArray($original->toArray());

        $this->assertCount(count($original->schemes), $restored->schemes);
        $this->assertCount(count($original->routes), $restored->routes);
        $this->assertEquals(
            $original->schemes['sanctumAuth']->name,
            $restored->schemes['sanctumAuth']->name
        );
    }

    #[Test]
    public function it_checks_if_empty(): void
    {
        $empty = new AuthenticationResult(schemes: [], routes: []);
        $this->assertTrue($empty->isEmpty());

        $withSchemes = new AuthenticationResult(
            schemes: ['test' => $this->sanctumScheme],
            routes: [],
        );
        $this->assertFalse($withSchemes->isEmpty());

        $withRoutes = new AuthenticationResult(
            schemes: [],
            routes: [0 => new RouteAuthentication(
                scheme: $this->sanctumScheme,
                middleware: ['auth'],
            )],
        );
        $this->assertFalse($withRoutes->isEmpty());
    }

    #[Test]
    public function it_checks_if_has_schemes(): void
    {
        $empty = AuthenticationResult::empty();
        $this->assertFalse($empty->hasSchemes());

        $withScheme = new AuthenticationResult(
            schemes: ['sanctumAuth' => $this->sanctumScheme],
            routes: [],
        );
        $this->assertTrue($withScheme->hasSchemes());
    }

    #[Test]
    public function it_gets_scheme_by_name(): void
    {
        $result = new AuthenticationResult(
            schemes: [
                'sanctumAuth' => $this->sanctumScheme,
                'basicAuth' => $this->basicScheme,
            ],
            routes: [],
        );

        $this->assertSame($this->sanctumScheme, $result->getScheme('sanctumAuth'));
        $this->assertSame($this->basicScheme, $result->getScheme('basicAuth'));
        $this->assertNull($result->getScheme('nonexistent'));
    }

    #[Test]
    public function it_gets_route_authentication_by_index(): void
    {
        $route0Auth = new RouteAuthentication(
            scheme: $this->sanctumScheme,
            middleware: ['auth:sanctum'],
        );

        $route1Auth = new RouteAuthentication(
            scheme: $this->basicScheme,
            middleware: ['auth.basic'],
        );

        $result = new AuthenticationResult(
            schemes: [],
            routes: [0 => $route0Auth, 1 => $route1Auth],
        );

        $this->assertSame($route0Auth, $result->getRouteAuthentication(0));
        $this->assertSame($route1Auth, $result->getRouteAuthentication(1));
        $this->assertNull($result->getRouteAuthentication(99));
    }

    #[Test]
    public function it_has_route_authentication(): void
    {
        $result = new AuthenticationResult(
            schemes: [],
            routes: [
                0 => new RouteAuthentication(
                    scheme: $this->sanctumScheme,
                    middleware: ['auth:sanctum'],
                ),
            ],
        );

        $this->assertTrue($result->hasRouteAuthentication(0));
        $this->assertFalse($result->hasRouteAuthentication(1));
        $this->assertFalse($result->hasRouteAuthentication(99));
    }

    #[Test]
    public function it_counts_schemes(): void
    {
        $result = new AuthenticationResult(
            schemes: [
                'sanctumAuth' => $this->sanctumScheme,
                'basicAuth' => $this->basicScheme,
            ],
            routes: [],
        );

        $this->assertEquals(2, $result->countSchemes());
    }

    #[Test]
    public function it_counts_authenticated_routes(): void
    {
        $result = new AuthenticationResult(
            schemes: [],
            routes: [
                0 => new RouteAuthentication(
                    scheme: $this->sanctumScheme,
                    middleware: ['auth:sanctum'],
                ),
                5 => new RouteAuthentication(
                    scheme: $this->basicScheme,
                    middleware: ['auth.basic'],
                ),
            ],
        );

        $this->assertEquals(2, $result->countAuthenticatedRoutes());
    }

    #[Test]
    public function it_gets_all_scheme_names(): void
    {
        $result = new AuthenticationResult(
            schemes: [
                'sanctumAuth' => $this->sanctumScheme,
                'basicAuth' => $this->basicScheme,
            ],
            routes: [],
        );

        $names = $result->getSchemeNames();

        $this->assertCount(2, $names);
        $this->assertContains('sanctumAuth', $names);
        $this->assertContains('basicAuth', $names);
    }
}
