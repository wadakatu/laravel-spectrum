<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\OpenApiServer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OpenApiServerTest extends TestCase
{
    #[Test]
    public function it_can_be_constructed_with_url_only(): void
    {
        $server = new OpenApiServer(
            url: 'https://api.example.com',
        );

        $this->assertEquals('https://api.example.com', $server->url);
        $this->assertEquals('', $server->description);
        $this->assertNull($server->variables);
    }

    #[Test]
    public function it_can_be_constructed_with_all_fields(): void
    {
        $server = new OpenApiServer(
            url: 'https://{environment}.example.com',
            description: 'API server with environment variable',
            variables: [
                'environment' => [
                    'default' => 'api',
                    'description' => 'Server environment',
                    'enum' => ['api', 'staging', 'production'],
                ],
            ],
        );

        $this->assertEquals('https://{environment}.example.com', $server->url);
        $this->assertEquals('API server with environment variable', $server->description);
        $this->assertIsArray($server->variables);
        $this->assertArrayHasKey('environment', $server->variables);
    }

    #[Test]
    public function it_converts_to_array_with_url_only(): void
    {
        $server = new OpenApiServer(
            url: 'https://api.example.com',
        );

        $array = $server->toArray();

        $this->assertEquals('https://api.example.com', $array['url']);
        $this->assertArrayNotHasKey('description', $array);
        $this->assertArrayNotHasKey('variables', $array);
    }

    #[Test]
    public function it_converts_to_array_with_all_fields(): void
    {
        $server = new OpenApiServer(
            url: 'https://{host}/api',
            description: 'Dynamic host server',
            variables: [
                'host' => ['default' => 'example.com'],
            ],
        );

        $array = $server->toArray();

        $this->assertEquals('https://{host}/api', $array['url']);
        $this->assertEquals('Dynamic host server', $array['description']);
        $this->assertEquals(['host' => ['default' => 'example.com']], $array['variables']);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $data = [
            'url' => 'https://api.test.com',
            'description' => 'Test server',
            'variables' => [
                'version' => [
                    'default' => 'v1',
                    'enum' => ['v1', 'v2'],
                ],
            ],
        ];

        $server = OpenApiServer::fromArray($data);

        $this->assertEquals('https://api.test.com', $server->url);
        $this->assertEquals('Test server', $server->description);
        $this->assertEquals(['version' => ['default' => 'v1', 'enum' => ['v1', 'v2']]], $server->variables);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $data = [];

        $server = OpenApiServer::fromArray($data);

        $this->assertEquals('', $server->url);
        $this->assertEquals('', $server->description);
        $this->assertNull($server->variables);
    }

    #[Test]
    public function it_checks_if_has_variables(): void
    {
        $with = new OpenApiServer(
            url: 'https://api.example.com',
            variables: ['env' => ['default' => 'prod']],
        );
        $without = new OpenApiServer(
            url: 'https://api.example.com',
        );
        $empty = new OpenApiServer(
            url: 'https://api.example.com',
            variables: [],
        );

        $this->assertTrue($with->hasVariables());
        $this->assertFalse($without->hasVariables());
        $this->assertFalse($empty->hasVariables());
    }

    #[Test]
    public function it_checks_if_has_description(): void
    {
        $with = new OpenApiServer(
            url: 'https://api.example.com',
            description: 'Production server',
        );
        $without = new OpenApiServer(
            url: 'https://api.example.com',
        );
        $empty = new OpenApiServer(
            url: 'https://api.example.com',
            description: '',
        );

        $this->assertTrue($with->hasDescription());
        $this->assertFalse($without->hasDescription());
        $this->assertFalse($empty->hasDescription());
    }

    #[Test]
    public function it_survives_round_trip_serialization(): void
    {
        $original = new OpenApiServer(
            url: 'https://{env}.example.com/api/{version}',
            description: 'Multi-variable server',
            variables: [
                'env' => ['default' => 'api', 'enum' => ['api', 'staging']],
                'version' => ['default' => 'v1'],
            ],
        );

        $restored = OpenApiServer::fromArray($original->toArray());

        $this->assertEquals($original->url, $restored->url);
        $this->assertEquals($original->description, $restored->description);
        $this->assertEquals($original->variables, $restored->variables);
    }
}
