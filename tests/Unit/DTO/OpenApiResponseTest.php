<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\OpenApiResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OpenApiResponseTest extends TestCase
{
    #[Test]
    public function it_can_be_constructed(): void
    {
        $response = new OpenApiResponse(
            statusCode: '200',
            description: 'Successful response',
            content: [
                'application/json' => [
                    'schema' => ['type' => 'object'],
                ],
            ],
        );

        $this->assertEquals('200', $response->statusCode);
        $this->assertEquals('Successful response', $response->description);
        $this->assertIsArray($response->content);
    }

    #[Test]
    public function it_can_be_constructed_with_integer_status_code(): void
    {
        $response = new OpenApiResponse(
            statusCode: 201,
            description: 'Created',
        );

        $this->assertEquals(201, $response->statusCode);
    }

    #[Test]
    public function it_can_be_constructed_without_content(): void
    {
        $response = new OpenApiResponse(
            statusCode: '204',
            description: 'No Content',
        );

        $this->assertNull($response->content);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $response = new OpenApiResponse(
            statusCode: '200',
            description: 'Success',
            content: [
                'application/json' => [
                    'schema' => ['$ref' => '#/components/schemas/User'],
                ],
            ],
        );

        $array = $response->toArray();

        $this->assertEquals([
            'description' => 'Success',
            'content' => [
                'application/json' => [
                    'schema' => ['$ref' => '#/components/schemas/User'],
                ],
            ],
        ], $array);
    }

    #[Test]
    public function it_converts_to_array_without_content(): void
    {
        $response = new OpenApiResponse(
            statusCode: '204',
            description: 'No Content',
        );

        $array = $response->toArray();

        $this->assertEquals([
            'description' => 'No Content',
        ], $array);
    }

    #[Test]
    public function it_converts_to_array_with_links(): void
    {
        $response = new OpenApiResponse(
            statusCode: '201',
            description: 'Created',
            links: [
                'GetUserById' => [
                    'operationId' => 'usersShow',
                    'parameters' => ['user' => '$response.body#/id'],
                ],
            ],
        );

        $array = $response->toArray();

        $this->assertArrayHasKey('links', $array);
        $this->assertArrayHasKey('GetUserById', $array['links']);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $data = [
            'status_code' => '200',
            'description' => 'OK',
            'content' => [
                'application/json' => [
                    'schema' => ['type' => 'string'],
                ],
            ],
        ];

        $response = OpenApiResponse::fromArray($data);

        $this->assertEquals('200', $response->statusCode);
        $this->assertEquals('OK', $response->description);
        $this->assertNotNull($response->content);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $response = OpenApiResponse::fromArray([
            'status_code' => '500',
        ]);

        $this->assertEquals('500', $response->statusCode);
        $this->assertEquals('', $response->description);
        $this->assertNull($response->content);
    }

    #[Test]
    public function it_checks_if_has_content(): void
    {
        $withContent = new OpenApiResponse(
            statusCode: '200',
            description: 'OK',
            content: ['application/json' => []],
        );
        $withoutContent = new OpenApiResponse(
            statusCode: '204',
            description: 'No Content',
        );

        $this->assertTrue($withContent->hasContent());
        $this->assertFalse($withoutContent->hasContent());
    }

    #[Test]
    public function it_checks_if_has_links(): void
    {
        $withLinks = new OpenApiResponse(
            statusCode: '200',
            description: 'OK',
            links: ['GetUserById' => ['operationId' => 'usersShow']],
        );
        $withoutLinks = new OpenApiResponse(
            statusCode: '200',
            description: 'OK',
        );

        $this->assertTrue($withLinks->hasLinks());
        $this->assertFalse($withoutLinks->hasLinks());
    }

    #[Test]
    public function it_checks_if_is_success(): void
    {
        $ok = new OpenApiResponse('200', 'OK');
        $created = new OpenApiResponse('201', 'Created');
        $noContent = new OpenApiResponse('204', 'No Content');
        $badRequest = new OpenApiResponse('400', 'Bad Request');
        $serverError = new OpenApiResponse('500', 'Internal Server Error');

        $this->assertTrue($ok->isSuccess());
        $this->assertTrue($created->isSuccess());
        $this->assertTrue($noContent->isSuccess());
        $this->assertFalse($badRequest->isSuccess());
        $this->assertFalse($serverError->isSuccess());
    }

    #[Test]
    public function it_checks_if_is_error(): void
    {
        $ok = new OpenApiResponse('200', 'OK');
        $badRequest = new OpenApiResponse('400', 'Bad Request');
        $unauthorized = new OpenApiResponse('401', 'Unauthorized');
        $notFound = new OpenApiResponse('404', 'Not Found');
        $serverError = new OpenApiResponse('500', 'Internal Server Error');

        $this->assertFalse($ok->isError());
        $this->assertTrue($badRequest->isError());
        $this->assertTrue($unauthorized->isError());
        $this->assertTrue($notFound->isError());
        $this->assertTrue($serverError->isError());
    }

    #[Test]
    public function it_checks_if_is_client_error(): void
    {
        $badRequest = new OpenApiResponse('400', 'Bad Request');
        $serverError = new OpenApiResponse('500', 'Server Error');
        $ok = new OpenApiResponse('200', 'OK');

        $this->assertTrue($badRequest->isClientError());
        $this->assertFalse($serverError->isClientError());
        $this->assertFalse($ok->isClientError());
    }

    #[Test]
    public function it_checks_if_is_server_error(): void
    {
        $serverError = new OpenApiResponse('500', 'Server Error');
        $badGateway = new OpenApiResponse('502', 'Bad Gateway');
        $badRequest = new OpenApiResponse('400', 'Bad Request');
        $ok = new OpenApiResponse('200', 'OK');

        $this->assertTrue($serverError->isServerError());
        $this->assertTrue($badGateway->isServerError());
        $this->assertFalse($badRequest->isServerError());
        $this->assertFalse($ok->isServerError());
    }

    #[Test]
    public function it_gets_status_code_as_string(): void
    {
        $stringCode = new OpenApiResponse('200', 'OK');
        $intCode = new OpenApiResponse(201, 'Created');

        $this->assertEquals('200', $stringCode->getStatusCodeAsString());
        $this->assertEquals('201', $intCode->getStatusCodeAsString());
    }

    #[Test]
    public function it_survives_serialization_round_trip(): void
    {
        $original = new OpenApiResponse(
            statusCode: '200',
            description: 'Successful response',
            content: [
                'application/json' => [
                    'schema' => ['type' => 'object'],
                    'example' => ['id' => 1],
                ],
            ],
        );

        $restored = OpenApiResponse::fromArray([
            'status_code' => $original->statusCode,
            'description' => $original->description,
            'content' => $original->content,
        ]);

        $this->assertEquals($original->statusCode, $restored->statusCode);
        $this->assertEquals($original->description, $restored->description);
        $this->assertEquals($original->content, $restored->content);
    }

    #[Test]
    public function it_creates_from_array_with_camel_case_status_code(): void
    {
        $response = OpenApiResponse::fromArray([
            'statusCode' => '404',
            'description' => 'Not Found',
        ]);

        $this->assertEquals('404', $response->statusCode);
        $this->assertEquals('Not Found', $response->description);
    }

    #[Test]
    public function it_creates_from_array_with_links(): void
    {
        $response = OpenApiResponse::fromArray([
            'statusCode' => '201',
            'description' => 'Created',
            'links' => [
                'GetUserById' => [
                    'operationId' => 'usersShow',
                ],
            ],
        ]);

        $this->assertTrue($response->hasLinks());
        $this->assertArrayHasKey('GetUserById', $response->links ?? []);
    }
}
