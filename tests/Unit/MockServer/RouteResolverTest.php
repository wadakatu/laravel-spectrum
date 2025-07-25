<?php

namespace LaravelSpectrum\Tests\Unit\MockServer;

use LaravelSpectrum\MockServer\RouteResolver;
use PHPUnit\Framework\TestCase;

class RouteResolverTest extends TestCase
{
    private RouteResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new RouteResolver;
    }

    public function test_resolves_exact_path_match(): void
    {
        $openapi = [
            'paths' => [
                '/api/users' => [
                    'get' => [
                        'summary' => 'Get all users',
                    ],
                ],
            ],
        ];

        $route = $this->resolver->resolve('/api/users', 'get', $openapi);

        $this->assertNotNull($route);
        $this->assertEquals('/api/users', $route['path']);
        $this->assertEquals('get', $route['method']);
        $this->assertEquals(['summary' => 'Get all users'], $route['operation']);
    }

    public function test_resolves_path_with_parameters(): void
    {
        $openapi = [
            'paths' => [
                '/api/users/{id}' => [
                    'get' => [
                        'summary' => 'Get user by ID',
                        'parameters' => [
                            [
                                'name' => 'id',
                                'in' => 'path',
                                'required' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $route = $this->resolver->resolve('/api/users/123', 'get', $openapi);

        $this->assertNotNull($route);
        $this->assertEquals('/api/users/{id}', $route['path']);
        $this->assertEquals('get', $route['method']);
        $this->assertArrayHasKey('params', $route);
        $this->assertEquals('123', $route['params']['id']);
    }

    public function test_resolves_complex_path_with_multiple_parameters(): void
    {
        $openapi = [
            'paths' => [
                '/api/organizations/{orgId}/users/{userId}/posts/{postId}' => [
                    'get' => [
                        'summary' => 'Get specific post',
                    ],
                ],
            ],
        ];

        $route = $this->resolver->resolve('/api/organizations/org-123/users/user-456/posts/post-789', 'get', $openapi);

        $this->assertNotNull($route);
        $this->assertEquals('/api/organizations/{orgId}/users/{userId}/posts/{postId}', $route['path']);
        $this->assertEquals('org-123', $route['params']['orgId']);
        $this->assertEquals('user-456', $route['params']['userId']);
        $this->assertEquals('post-789', $route['params']['postId']);
    }

    public function test_returns_null_for_non_existent_path(): void
    {
        $openapi = [
            'paths' => [
                '/api/users' => [
                    'get' => ['summary' => 'Get users'],
                ],
            ],
        ];

        $route = $this->resolver->resolve('/api/posts', 'get', $openapi);

        $this->assertNull($route);
    }

    public function test_returns_null_for_wrong_method(): void
    {
        $openapi = [
            'paths' => [
                '/api/users' => [
                    'get' => ['summary' => 'Get users'],
                ],
            ],
        ];

        $route = $this->resolver->resolve('/api/users', 'post', $openapi);

        $this->assertNull($route);
    }

    public function test_handles_trailing_slash(): void
    {
        $openapi = [
            'paths' => [
                '/api/users' => [
                    'get' => ['summary' => 'Get users'],
                ],
            ],
        ];

        $route = $this->resolver->resolve('/api/users/', 'get', $openapi);

        $this->assertNotNull($route);
        $this->assertEquals('/api/users', $route['path']);
    }

    public function test_prioritizes_exact_match_over_parameterized_route(): void
    {
        $openapi = [
            'paths' => [
                '/api/users/me' => [
                    'get' => ['summary' => 'Get current user'],
                ],
                '/api/users/{id}' => [
                    'get' => ['summary' => 'Get user by ID'],
                ],
            ],
        ];

        $route = $this->resolver->resolve('/api/users/me', 'get', $openapi);

        $this->assertNotNull($route);
        $this->assertEquals('/api/users/me', $route['path']);
        $this->assertEquals('Get current user', $route['operation']['summary']);
    }

    public function test_handles_special_characters_in_parameters(): void
    {
        $openapi = [
            'paths' => [
                '/api/search/{query}' => [
                    'get' => ['summary' => 'Search'],
                ],
            ],
        ];

        $route = $this->resolver->resolve('/api/search/hello%20world', 'get', $openapi);

        $this->assertNotNull($route);
        $this->assertEquals('hello world', $route['params']['query']);
    }

    public function test_matches_route_with_query_parameters(): void
    {
        $openapi = [
            'paths' => [
                '/api/users' => [
                    'get' => [
                        'summary' => 'Get users',
                        'parameters' => [
                            [
                                'name' => 'page',
                                'in' => 'query',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // Query parameters should not affect route matching
        $route = $this->resolver->resolve('/api/users?page=2&limit=10', 'get', $openapi);

        $this->assertNotNull($route);
        $this->assertEquals('/api/users', $route['path']);
    }

    public function test_stores_original_request_path(): void
    {
        $openapi = [
            'paths' => [
                '/api/users/{id}' => [
                    'get' => ['summary' => 'Get user'],
                ],
            ],
        ];

        $route = $this->resolver->resolve('/api/users/123', 'get', $openapi);

        $this->assertNotNull($route);
        $this->assertEquals('/api/users/123', $route['params']['_path']);
    }
}
