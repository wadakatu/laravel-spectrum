<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\OpenApiInfo;
use LaravelSpectrum\DTO\OpenApiServer;
use LaravelSpectrum\DTO\OpenApiSpec;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OpenApiSpecTest extends TestCase
{
    #[Test]
    public function it_can_be_constructed_with_required_fields(): void
    {
        $info = new OpenApiInfo(title: 'My API', version: '1.0.0');
        $spec = new OpenApiSpec(
            openapi: '3.0.0',
            info: $info,
        );

        $this->assertEquals('3.0.0', $spec->openapi);
        $this->assertSame($info, $spec->info);
        $this->assertEquals([], $spec->servers);
        $this->assertEquals([], $spec->paths);
        $this->assertEquals([], $spec->components);
        $this->assertEquals([], $spec->security);
        $this->assertEquals([], $spec->tags);
        $this->assertNull($spec->tagGroups);
    }

    #[Test]
    public function it_can_be_constructed_with_all_fields(): void
    {
        $info = new OpenApiInfo(title: 'Complete API', version: '2.0.0');
        $server = new OpenApiServer(url: 'https://api.example.com');

        $spec = new OpenApiSpec(
            openapi: '3.1.0',
            info: $info,
            servers: [$server],
            paths: ['/users' => ['get' => ['operationId' => 'getUsers']]],
            components: ['schemas' => ['User' => ['type' => 'object']]],
            security: [['bearerAuth' => []]],
            tags: [['name' => 'Users', 'description' => 'User operations']],
            tagGroups: [['name' => 'Core', 'tags' => ['Users']]],
        );

        $this->assertEquals('3.1.0', $spec->openapi);
        $this->assertSame($info, $spec->info);
        $this->assertCount(1, $spec->servers);
        $this->assertSame($server, $spec->servers[0]);
        $this->assertArrayHasKey('/users', $spec->paths);
        $this->assertArrayHasKey('schemas', $spec->components);
        $this->assertCount(1, $spec->security);
        $this->assertCount(1, $spec->tags);
        $this->assertCount(1, $spec->tagGroups);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $info = new OpenApiInfo(title: 'Test API', version: '1.0.0');
        $server = new OpenApiServer(url: 'https://api.test.com');

        $spec = new OpenApiSpec(
            openapi: '3.0.0',
            info: $info,
            servers: [$server],
            paths: ['/items' => ['get' => ['summary' => 'List items']]],
            components: ['schemas' => ['Item' => ['type' => 'object']]],
            security: [['apiKey' => []]],
            tags: [['name' => 'Items']],
            tagGroups: [['name' => 'Resources', 'tags' => ['Items']]],
        );

        $array = $spec->toArray();

        $this->assertEquals('3.0.0', $array['openapi']);
        $this->assertIsArray($array['info']);
        $this->assertEquals('Test API', $array['info']['title']);
        $this->assertIsArray($array['servers']);
        $this->assertEquals('https://api.test.com', $array['servers'][0]['url']);
        $this->assertArrayHasKey('/items', $array['paths']);
        $this->assertArrayHasKey('schemas', $array['components']);
        $this->assertEquals([['apiKey' => []]], $array['security']);
        $this->assertEquals([['name' => 'Items']], $array['tags']);
        $this->assertEquals([['name' => 'Resources', 'tags' => ['Items']]], $array['x-tagGroups']);
    }

    #[Test]
    public function it_includes_required_fields_and_excludes_optional_empty_fields(): void
    {
        $info = new OpenApiInfo(title: 'Minimal API', version: '1.0.0');
        $spec = new OpenApiSpec(
            openapi: '3.0.0',
            info: $info,
        );

        $array = $spec->toArray();

        // Required fields are always present
        $this->assertArrayHasKey('openapi', $array);
        $this->assertArrayHasKey('info', $array);
        $this->assertArrayHasKey('servers', $array);
        $this->assertArrayHasKey('paths', $array);
        $this->assertArrayHasKey('components', $array);
        // Empty arrays are still empty
        $this->assertEquals([], $array['servers']);
        $this->assertEquals([], $array['paths']);
        $this->assertEquals([], $array['components']);
        // Optional fields are excluded when not set
        $this->assertArrayNotHasKey('security', $array);
        $this->assertArrayNotHasKey('tags', $array);
        $this->assertArrayNotHasKey('x-tagGroups', $array);
        $this->assertArrayNotHasKey('webhooks', $array);
    }

    #[Test]
    public function it_includes_webhooks_in_array_when_set(): void
    {
        $info = new OpenApiInfo(title: 'API', version: '1.0.0');
        $webhooksData = new \stdClass;
        $spec = new OpenApiSpec(
            openapi: '3.1.0',
            info: $info,
            webhooks: $webhooksData,
        );

        $array = $spec->toArray();

        $this->assertArrayHasKey('webhooks', $array);
        $this->assertSame($webhooksData, $array['webhooks']);
    }

    #[Test]
    public function it_includes_webhooks_array_in_output(): void
    {
        $info = new OpenApiInfo(title: 'API', version: '1.0.0');
        $webhooksData = [
            'newUser' => [
                'post' => [
                    'requestBody' => [
                        'content' => ['application/json' => ['schema' => ['type' => 'object']]],
                    ],
                ],
            ],
        ];
        $spec = new OpenApiSpec(
            openapi: '3.1.0',
            info: $info,
            webhooks: $webhooksData,
        );

        $array = $spec->toArray();

        $this->assertArrayHasKey('webhooks', $array);
        $this->assertArrayHasKey('newUser', $array['webhooks']);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $data = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'From Array API',
                'version' => '1.0.0',
                'description' => 'Created from array',
            ],
            'servers' => [
                ['url' => 'https://api.example.com'],
            ],
            'paths' => [
                '/users' => ['get' => ['operationId' => 'getUsers']],
            ],
            'components' => [
                'schemas' => ['User' => ['type' => 'object']],
                'securitySchemes' => ['bearerAuth' => ['type' => 'http', 'scheme' => 'bearer']],
            ],
            'security' => [['bearerAuth' => []]],
            'tags' => [['name' => 'Users']],
            'x-tagGroups' => [['name' => 'All', 'tags' => ['Users']]],
        ];

        $spec = OpenApiSpec::fromArray($data);

        $this->assertEquals('3.0.0', $spec->openapi);
        $this->assertInstanceOf(OpenApiInfo::class, $spec->info);
        $this->assertEquals('From Array API', $spec->info->title);
        $this->assertCount(1, $spec->servers);
        $this->assertInstanceOf(OpenApiServer::class, $spec->servers[0]);
        $this->assertEquals('https://api.example.com', $spec->servers[0]->url);
        $this->assertArrayHasKey('/users', $spec->paths);
        $this->assertArrayHasKey('schemas', $spec->components);
        $this->assertArrayHasKey('securitySchemes', $spec->components);
        $this->assertCount(1, $spec->security);
        $this->assertCount(1, $spec->tags);
        $this->assertCount(1, $spec->tagGroups);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $data = [];

        $spec = OpenApiSpec::fromArray($data);

        $this->assertEquals('3.0.0', $spec->openapi);
        $this->assertInstanceOf(OpenApiInfo::class, $spec->info);
        $this->assertEquals('', $spec->info->title);
        $this->assertEquals([], $spec->servers);
        $this->assertEquals([], $spec->paths);
        $this->assertEquals([], $spec->components);
        $this->assertEquals([], $spec->security);
        $this->assertEquals([], $spec->tags);
        $this->assertNull($spec->tagGroups);
    }

    #[Test]
    public function it_creates_from_array_with_existing_server_dtos(): void
    {
        $server = new OpenApiServer(url: 'https://api.example.com', description: 'Production');

        $data = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'API', 'version' => '1.0.0'],
            'servers' => [$server],
        ];

        $spec = OpenApiSpec::fromArray($data);

        $this->assertCount(1, $spec->servers);
        $this->assertSame($server, $spec->servers[0]);
    }

    #[Test]
    public function it_gets_schemas_from_components(): void
    {
        $info = new OpenApiInfo(title: 'API', version: '1.0.0');
        $spec = new OpenApiSpec(
            openapi: '3.0.0',
            info: $info,
            components: [
                'schemas' => [
                    'User' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']]],
                    'Post' => ['type' => 'object', 'properties' => ['title' => ['type' => 'string']]],
                ],
            ],
        );

        $schemas = $spec->getSchemas();

        $this->assertCount(2, $schemas);
        $this->assertArrayHasKey('User', $schemas);
        $this->assertArrayHasKey('Post', $schemas);
    }

    #[Test]
    public function it_returns_empty_array_when_no_schemas(): void
    {
        $info = new OpenApiInfo(title: 'API', version: '1.0.0');
        $spec = new OpenApiSpec(
            openapi: '3.0.0',
            info: $info,
        );

        $schemas = $spec->getSchemas();

        $this->assertEquals([], $schemas);
    }

    #[Test]
    public function it_gets_security_schemes_from_components(): void
    {
        $info = new OpenApiInfo(title: 'API', version: '1.0.0');
        $spec = new OpenApiSpec(
            openapi: '3.0.0',
            info: $info,
            components: [
                'securitySchemes' => [
                    'bearerAuth' => ['type' => 'http', 'scheme' => 'bearer'],
                    'apiKey' => ['type' => 'apiKey', 'in' => 'header', 'name' => 'X-API-Key'],
                ],
            ],
        );

        $schemes = $spec->getSecuritySchemes();

        $this->assertCount(2, $schemes);
        $this->assertArrayHasKey('bearerAuth', $schemes);
        $this->assertArrayHasKey('apiKey', $schemes);
    }

    #[Test]
    public function it_returns_empty_array_when_no_security_schemes(): void
    {
        $info = new OpenApiInfo(title: 'API', version: '1.0.0');
        $spec = new OpenApiSpec(
            openapi: '3.0.0',
            info: $info,
        );

        $schemes = $spec->getSecuritySchemes();

        $this->assertEquals([], $schemes);
    }

    #[Test]
    public function it_checks_if_has_global_security(): void
    {
        $info = new OpenApiInfo(title: 'API', version: '1.0.0');

        $with = new OpenApiSpec(
            openapi: '3.0.0',
            info: $info,
            security: [['bearerAuth' => []]],
        );
        $without = new OpenApiSpec(
            openapi: '3.0.0',
            info: $info,
        );
        $empty = new OpenApiSpec(
            openapi: '3.0.0',
            info: $info,
            security: [],
        );

        $this->assertTrue($with->hasGlobalSecurity());
        $this->assertFalse($without->hasGlobalSecurity());
        $this->assertFalse($empty->hasGlobalSecurity());
    }

    #[Test]
    public function it_checks_if_has_servers(): void
    {
        $info = new OpenApiInfo(title: 'API', version: '1.0.0');
        $server = new OpenApiServer(url: 'https://api.example.com');

        $with = new OpenApiSpec(
            openapi: '3.0.0',
            info: $info,
            servers: [$server],
        );
        $without = new OpenApiSpec(
            openapi: '3.0.0',
            info: $info,
        );

        $this->assertTrue($with->hasServers());
        $this->assertFalse($without->hasServers());
    }

    #[Test]
    public function it_checks_if_has_paths(): void
    {
        $info = new OpenApiInfo(title: 'API', version: '1.0.0');

        $with = new OpenApiSpec(
            openapi: '3.0.0',
            info: $info,
            paths: ['/users' => ['get' => []]],
        );
        $without = new OpenApiSpec(
            openapi: '3.0.0',
            info: $info,
        );

        $this->assertTrue($with->hasPaths());
        $this->assertFalse($without->hasPaths());
    }

    #[Test]
    public function it_checks_if_has_components(): void
    {
        $info = new OpenApiInfo(title: 'API', version: '1.0.0');

        $with = new OpenApiSpec(
            openapi: '3.0.0',
            info: $info,
            components: ['schemas' => ['User' => []]],
        );
        $without = new OpenApiSpec(
            openapi: '3.0.0',
            info: $info,
        );

        $this->assertTrue($with->hasComponents());
        $this->assertFalse($without->hasComponents());
    }

    #[Test]
    public function it_checks_if_has_tags(): void
    {
        $info = new OpenApiInfo(title: 'API', version: '1.0.0');

        $with = new OpenApiSpec(
            openapi: '3.0.0',
            info: $info,
            tags: [['name' => 'Users']],
        );
        $without = new OpenApiSpec(
            openapi: '3.0.0',
            info: $info,
        );

        $this->assertTrue($with->hasTags());
        $this->assertFalse($without->hasTags());
    }

    #[Test]
    public function it_checks_if_has_tag_groups(): void
    {
        $info = new OpenApiInfo(title: 'API', version: '1.0.0');

        $with = new OpenApiSpec(
            openapi: '3.0.0',
            info: $info,
            tagGroups: [['name' => 'All', 'tags' => ['Users']]],
        );
        $without = new OpenApiSpec(
            openapi: '3.0.0',
            info: $info,
        );

        $this->assertTrue($with->hasTagGroups());
        $this->assertFalse($without->hasTagGroups());
    }

    #[Test]
    public function it_gets_path_by_path_string(): void
    {
        $info = new OpenApiInfo(title: 'API', version: '1.0.0');
        $spec = new OpenApiSpec(
            openapi: '3.0.0',
            info: $info,
            paths: [
                '/users' => ['get' => ['operationId' => 'getUsers']],
                '/users/{id}' => ['get' => ['operationId' => 'getUser']],
            ],
        );

        $usersPath = $spec->getPath('/users');
        $userPath = $spec->getPath('/users/{id}');
        $nonExistent = $spec->getPath('/posts');

        $this->assertIsArray($usersPath);
        $this->assertArrayHasKey('get', $usersPath);
        $this->assertEquals('getUsers', $usersPath['get']['operationId']);
        $this->assertIsArray($userPath);
        $this->assertEquals('getUser', $userPath['get']['operationId']);
        $this->assertNull($nonExistent);
    }

    #[Test]
    public function it_gets_version_parts(): void
    {
        $info = new OpenApiInfo(title: 'API', version: '1.0.0');

        $spec300 = new OpenApiSpec(openapi: '3.0.0', info: $info);
        $spec310 = new OpenApiSpec(openapi: '3.1.0', info: $info);
        $spec312 = new OpenApiSpec(openapi: '3.1.2', info: $info);

        $this->assertEquals(['major' => 3, 'minor' => 0, 'patch' => 0], $spec300->getVersionParts());
        $this->assertEquals(['major' => 3, 'minor' => 1, 'patch' => 0], $spec310->getVersionParts());
        $this->assertEquals(['major' => 3, 'minor' => 1, 'patch' => 2], $spec312->getVersionParts());
    }

    #[Test]
    public function it_checks_if_is_version_31(): void
    {
        $info = new OpenApiInfo(title: 'API', version: '1.0.0');

        $spec300 = new OpenApiSpec(openapi: '3.0.0', info: $info);
        $spec310 = new OpenApiSpec(openapi: '3.1.0', info: $info);
        $spec311 = new OpenApiSpec(openapi: '3.1.1', info: $info);

        $this->assertFalse($spec300->isVersion31());
        $this->assertTrue($spec310->isVersion31());
        $this->assertTrue($spec311->isVersion31());
    }

    #[Test]
    public function it_survives_round_trip_serialization(): void
    {
        $original = new OpenApiSpec(
            openapi: '3.1.0',
            info: new OpenApiInfo(
                title: 'Round Trip API',
                version: '2.0.0',
                description: 'Testing round trip',
            ),
            servers: [
                new OpenApiServer(url: 'https://api.example.com', description: 'Production'),
                new OpenApiServer(url: 'https://staging.example.com', description: 'Staging'),
            ],
            paths: [
                '/users' => ['get' => ['operationId' => 'getUsers', 'summary' => 'List users']],
                '/users/{id}' => ['get' => ['operationId' => 'getUser']],
            ],
            components: [
                'schemas' => ['User' => ['type' => 'object']],
                'securitySchemes' => ['bearerAuth' => ['type' => 'http', 'scheme' => 'bearer']],
            ],
            security: [['bearerAuth' => []]],
            tags: [['name' => 'Users', 'description' => 'User operations']],
            tagGroups: [['name' => 'Core', 'tags' => ['Users']]],
        );

        $restored = OpenApiSpec::fromArray($original->toArray());

        $this->assertEquals($original->openapi, $restored->openapi);
        $this->assertEquals($original->info->title, $restored->info->title);
        $this->assertEquals($original->info->version, $restored->info->version);
        $this->assertEquals($original->info->description, $restored->info->description);
        $this->assertCount(count($original->servers), $restored->servers);
        $this->assertEquals($original->servers[0]->url, $restored->servers[0]->url);
        $this->assertEquals($original->servers[1]->description, $restored->servers[1]->description);
        $this->assertEquals($original->paths, $restored->paths);
        $this->assertEquals($original->components, $restored->components);
        $this->assertEquals($original->security, $restored->security);
        $this->assertEquals($original->tags, $restored->tags);
        $this->assertEquals($original->tagGroups, $restored->tagGroups);
    }

    #[Test]
    public function it_checks_has_webhooks(): void
    {
        $info = new OpenApiInfo(title: 'API', version: '1.0.0');

        $specWithWebhooks = new OpenApiSpec(
            openapi: '3.1.0',
            info: $info,
            webhooks: new \stdClass,
        );

        $specWithoutWebhooks = new OpenApiSpec(
            openapi: '3.0.0',
            info: $info,
        );

        $this->assertTrue($specWithWebhooks->hasWebhooks());
        $this->assertFalse($specWithoutWebhooks->hasWebhooks());
    }
}
