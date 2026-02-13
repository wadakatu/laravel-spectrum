<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\ResponseLinkInfo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ResponseLinkInfoTest extends TestCase
{
    #[Test]
    public function it_can_be_constructed(): void
    {
        $link = new ResponseLinkInfo(
            statusCode: 201,
            name: 'GetUserById',
            operationId: 'usersShow',
            parameters: ['user' => '$response.body#/id'],
            description: 'Fetch the created user',
        );

        $this->assertSame(201, $link->statusCode);
        $this->assertSame('GetUserById', $link->name);
        $this->assertSame('usersShow', $link->operationId);
    }

    #[Test]
    public function it_creates_from_array_and_converts_to_link_object(): void
    {
        $link = ResponseLinkInfo::fromArray([
            'statusCode' => 200,
            'name' => 'GetUserPosts',
            'operationRef' => '#/paths/~1api~1users~1{user}~1posts/get',
            'parameters' => ['user' => '$response.body#/id'],
        ]);

        $this->assertSame(200, $link->statusCode);
        $this->assertSame('GetUserPosts', $link->name);
        $this->assertSame('#/paths/~1api~1users~1{user}~1posts/get', $link->operationRef);

        $this->assertSame([
            'operationRef' => '#/paths/~1api~1users~1{user}~1posts/get',
            'parameters' => ['user' => '$response.body#/id'],
        ], $link->toLinkObject());
    }

    #[Test]
    public function it_requires_operation_id_or_operation_ref(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ResponseLinkInfo::fromArray([
            'statusCode' => 200,
            'name' => 'invalid',
        ]);
    }

    #[Test]
    public function it_rejects_empty_operation_id_or_operation_ref(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ResponseLinkInfo::fromArray([
            'statusCode' => 200,
            'name' => 'invalid',
            'operationId' => '',
        ]);
    }

    #[Test]
    public function it_rejects_when_both_operation_id_and_operation_ref_are_provided(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ResponseLinkInfo::fromArray([
            'statusCode' => 200,
            'name' => 'invalid',
            'operationId' => 'usersShow',
            'operationRef' => '#/paths/~1api~1users~1{id}/get',
        ]);
    }

    #[Test]
    public function it_accepts_snake_case_keys(): void
    {
        $link = ResponseLinkInfo::fromArray([
            'status_code' => 200,
            'name' => 'GetUserById',
            'operation_id' => 'usersShow',
            'request_body' => '$response.body#/payload',
        ]);

        $this->assertSame(200, $link->statusCode);
        $this->assertSame('usersShow', $link->operationId);
        $this->assertSame('$response.body#/payload', $link->requestBody);
    }

    #[Test]
    public function it_rejects_invalid_status_code_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ResponseLinkInfo::fromArray([
            'statusCode' => ['200'],
            'name' => 'invalid',
            'operationId' => 'usersShow',
        ]);
    }
}
