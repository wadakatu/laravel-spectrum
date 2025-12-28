<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\OpenApiOperation;
use LaravelSpectrum\DTO\OpenApiParameter;
use LaravelSpectrum\DTO\OpenApiRequestBody;
use LaravelSpectrum\DTO\OpenApiSchema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OpenApiOperationTest extends TestCase
{
    #[Test]
    public function it_can_be_constructed(): void
    {
        $operation = new OpenApiOperation(
            operationId: 'getUsers',
            summary: 'Get all users',
            tags: ['Users'],
            parameters: [],
            responses: [],
        );

        $this->assertEquals('getUsers', $operation->operationId);
        $this->assertEquals('Get all users', $operation->summary);
        $this->assertEquals(['Users'], $operation->tags);
    }

    #[Test]
    public function it_can_be_constructed_with_all_fields(): void
    {
        $requestBody = new OpenApiRequestBody(
            content: ['application/json' => []],
            required: true,
        );

        $parameter = new OpenApiParameter(
            name: 'include',
            in: OpenApiParameter::IN_QUERY,
            required: false,
            schema: OpenApiSchema::string(),
        );

        $operation = new OpenApiOperation(
            operationId: 'createUser',
            summary: 'Create a user',
            tags: ['Users'],
            parameters: [$parameter],
            responses: [],
            description: 'Creates a new user in the system',
            requestBody: $requestBody,
            security: [['bearerAuth' => []]],
            deprecated: false,
        );

        $this->assertEquals('Creates a new user in the system', $operation->description);
        $this->assertSame($requestBody, $operation->requestBody);
        $this->assertEquals([['bearerAuth' => []]], $operation->security);
        $this->assertFalse($operation->deprecated);
    }

    #[Test]
    public function it_can_be_constructed_with_defaults(): void
    {
        $operation = new OpenApiOperation(
            operationId: 'listItems',
            summary: 'List items',
            tags: [],
            parameters: [],
            responses: [],
        );

        $this->assertNull($operation->description);
        $this->assertNull($operation->requestBody);
        $this->assertNull($operation->security);
        $this->assertFalse($operation->deprecated);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $parameter = new OpenApiParameter(
            name: 'id',
            in: OpenApiParameter::IN_PATH,
            required: true,
            schema: OpenApiSchema::string(),
        );

        $operation = new OpenApiOperation(
            operationId: 'getUser',
            summary: 'Get a user',
            tags: ['Users'],
            parameters: [$parameter],
            responses: [],
        );

        $array = $operation->toArray();

        $this->assertEquals('getUser', $array['operationId']);
        $this->assertEquals('Get a user', $array['summary']);
        $this->assertEquals(['Users'], $array['tags']);
        $this->assertCount(1, $array['parameters']);
        $this->assertEquals('id', $array['parameters'][0]['name']);
        $this->assertEquals('path', $array['parameters'][0]['in']);
        $this->assertTrue($array['parameters'][0]['required']);
    }

    #[Test]
    public function it_converts_to_array_with_all_fields(): void
    {
        $requestBody = new OpenApiRequestBody(
            content: ['application/json' => ['schema' => ['type' => 'object']]],
            required: true,
        );

        $operation = new OpenApiOperation(
            operationId: 'updateUser',
            summary: 'Update user',
            tags: ['Users'],
            parameters: [],
            responses: [],
            description: 'Updates an existing user',
            requestBody: $requestBody,
            security: [['apiKey' => []]],
            deprecated: true,
        );

        $array = $operation->toArray();

        $this->assertEquals('Updates an existing user', $array['description']);
        $this->assertEquals([['apiKey' => []]], $array['security']);
        $this->assertTrue($array['deprecated']);
        $this->assertArrayHasKey('requestBody', $array);
    }

    #[Test]
    public function it_excludes_null_fields_from_array(): void
    {
        $operation = new OpenApiOperation(
            operationId: 'simpleOp',
            summary: 'Simple',
            tags: [],
            parameters: [],
            responses: [],
        );

        $array = $operation->toArray();

        $this->assertArrayNotHasKey('description', $array);
        $this->assertArrayNotHasKey('requestBody', $array);
        $this->assertArrayNotHasKey('security', $array);
        $this->assertArrayNotHasKey('deprecated', $array);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $data = [
            'operationId' => 'deleteUser',
            'summary' => 'Delete a user',
            'tags' => ['Users', 'Admin'],
            'parameters' => [['name' => 'id', 'in' => 'path']],
            'responses' => [],
            'description' => 'Permanently deletes a user',
            'security' => [['bearerAuth' => []]],
            'deprecated' => true,
        ];

        $operation = OpenApiOperation::fromArray($data);

        $this->assertEquals('deleteUser', $operation->operationId);
        $this->assertEquals('Delete a user', $operation->summary);
        $this->assertEquals(['Users', 'Admin'], $operation->tags);
        $this->assertEquals('Permanently deletes a user', $operation->description);
        $this->assertTrue($operation->deprecated);
    }

    #[Test]
    public function it_creates_from_array_with_request_body(): void
    {
        $data = [
            'operationId' => 'createPost',
            'summary' => 'Create post',
            'tags' => [],
            'parameters' => [],
            'responses' => [],
            'requestBody' => [
                'content' => ['application/json' => []],
                'required' => true,
            ],
        ];

        $operation = OpenApiOperation::fromArray($data);

        $this->assertInstanceOf(OpenApiRequestBody::class, $operation->requestBody);
        $this->assertTrue($operation->requestBody->required);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $data = [
            'operationId' => 'minimalOp',
        ];

        $operation = OpenApiOperation::fromArray($data);

        $this->assertEquals('minimalOp', $operation->operationId);
        $this->assertNull($operation->summary);
        $this->assertEquals([], $operation->tags);
        $this->assertEquals([], $operation->parameters);
        $this->assertEquals([], $operation->responses);
    }

    #[Test]
    public function it_checks_if_has_parameters(): void
    {
        $parameter = new OpenApiParameter(
            name: 'id',
            in: OpenApiParameter::IN_PATH,
            required: true,
            schema: OpenApiSchema::string(),
        );

        $with = new OpenApiOperation(
            operationId: 'op1',
            summary: null,
            tags: [],
            parameters: [$parameter],
            responses: [],
        );
        $without = new OpenApiOperation(
            operationId: 'op2',
            summary: null,
            tags: [],
            parameters: [],
            responses: [],
        );

        $this->assertTrue($with->hasParameters());
        $this->assertFalse($without->hasParameters());
    }

    #[Test]
    public function it_checks_if_has_request_body(): void
    {
        $requestBody = new OpenApiRequestBody(content: ['application/json' => []]);
        $with = new OpenApiOperation(
            operationId: 'op1',
            summary: null,
            tags: [],
            parameters: [],
            responses: [],
            requestBody: $requestBody,
        );
        $without = new OpenApiOperation(
            operationId: 'op2',
            summary: null,
            tags: [],
            parameters: [],
            responses: [],
        );

        $this->assertTrue($with->hasRequestBody());
        $this->assertFalse($without->hasRequestBody());
    }

    #[Test]
    public function it_checks_if_has_security(): void
    {
        $with = new OpenApiOperation(
            operationId: 'op1',
            summary: null,
            tags: [],
            parameters: [],
            responses: [],
            security: [['bearerAuth' => []]],
        );
        $without = new OpenApiOperation(
            operationId: 'op2',
            summary: null,
            tags: [],
            parameters: [],
            responses: [],
        );
        $empty = new OpenApiOperation(
            operationId: 'op3',
            summary: null,
            tags: [],
            parameters: [],
            responses: [],
            security: [],
        );

        $this->assertTrue($with->hasSecurity());
        $this->assertFalse($without->hasSecurity());
        $this->assertFalse($empty->hasSecurity());
    }

    #[Test]
    public function it_checks_if_is_deprecated(): void
    {
        $deprecated = new OpenApiOperation(
            operationId: 'oldOp',
            summary: null,
            tags: [],
            parameters: [],
            responses: [],
            deprecated: true,
        );
        $notDeprecated = new OpenApiOperation(
            operationId: 'newOp',
            summary: null,
            tags: [],
            parameters: [],
            responses: [],
        );

        $this->assertTrue($deprecated->isDeprecated());
        $this->assertFalse($notDeprecated->isDeprecated());
    }

    #[Test]
    public function it_gets_tag_count(): void
    {
        $operation = new OpenApiOperation(
            operationId: 'op',
            summary: null,
            tags: ['Users', 'Admin', 'API'],
            parameters: [],
            responses: [],
        );

        $this->assertEquals(3, $operation->getTagCount());
    }

    #[Test]
    public function it_gets_zero_tag_count(): void
    {
        $operation = new OpenApiOperation(
            operationId: 'op',
            summary: null,
            tags: [],
            parameters: [],
            responses: [],
        );

        $this->assertEquals(0, $operation->getTagCount());
    }

    #[Test]
    public function it_survives_serialization_round_trip(): void
    {
        $requestBody = new OpenApiRequestBody(
            content: ['application/json' => ['schema' => ['type' => 'object']]],
            required: true,
        );

        $parameter = new OpenApiParameter(
            name: 'id',
            in: OpenApiParameter::IN_PATH,
            required: true,
            schema: OpenApiSchema::string(),
        );

        $original = new OpenApiOperation(
            operationId: 'fullOp',
            summary: 'Full operation',
            tags: ['Test'],
            parameters: [$parameter],
            responses: [],
            description: 'A full test operation',
            requestBody: $requestBody,
            security: [['apiKey' => []]],
            deprecated: true,
        );

        $restored = OpenApiOperation::fromArray($original->toArray());

        $this->assertEquals($original->operationId, $restored->operationId);
        $this->assertEquals($original->summary, $restored->summary);
        $this->assertEquals($original->tags, $restored->tags);
        $this->assertCount(count($original->parameters), $restored->parameters);
        $this->assertEquals($original->parameters[0]->name, $restored->parameters[0]->name);
        $this->assertEquals($original->parameters[0]->in, $restored->parameters[0]->in);
        $this->assertEquals($original->description, $restored->description);
        $this->assertEquals($original->deprecated, $restored->deprecated);
        $this->assertInstanceOf(OpenApiRequestBody::class, $restored->requestBody);
    }
}
