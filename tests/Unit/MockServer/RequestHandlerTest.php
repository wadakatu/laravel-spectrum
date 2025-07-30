<?php

namespace LaravelSpectrum\Tests\Unit\MockServer;

use LaravelSpectrum\MockServer\AuthenticationSimulator;
use LaravelSpectrum\MockServer\RequestHandler;
use LaravelSpectrum\MockServer\ResponseGenerator;
use LaravelSpectrum\MockServer\ValidationSimulator;
use PHPUnit\Framework\TestCase;
use Workerman\Protocols\Http\Request;

class RequestHandlerTest extends TestCase
{
    private RequestHandler $handler;

    private ValidationSimulator $validator;

    private AuthenticationSimulator $authenticator;

    private ResponseGenerator $responseGenerator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = $this->createMock(ValidationSimulator::class);
        $this->authenticator = $this->createMock(AuthenticationSimulator::class);
        $this->responseGenerator = $this->createMock(ResponseGenerator::class);

        $this->handler = new RequestHandler(
            $this->validator,
            $this->authenticator,
            $this->responseGenerator
        );
    }

    /**
     * Create a stub Request object with the given data
     */
    private function createRequestStub(array $config = []): Request
    {
        return new class($config) extends Request
        {
            private array $config;

            public function __construct(array $config)
            {
                $this->config = $config;
            }

            public function method(): string
            {
                return $this->config['method'] ?? 'GET';
            }

            public function get(?string $name = null, mixed $default = null): mixed
            {
                if ($name === null) {
                    return $this->config['get'] ?? [];
                }

                return ($this->config['get'] ?? [])[$name] ?? $default;
            }

            public function post(?string $name = null, mixed $default = null): mixed
            {
                if ($name === null) {
                    return $this->config['post'] ?? [];
                }

                return ($this->config['post'] ?? [])[$name] ?? $default;
            }

            public function header(?string $name = null, mixed $default = null): mixed
            {
                if ($name === null) {
                    return $this->config['headers'] ?? [];
                }

                return ($this->config['headers'] ?? [])[strtolower($name)] ?? $default;
            }

            public function rawBody(): string
            {
                return $this->config['rawBody'] ?? '';
            }
        };
    }

    public function test_handles_successful_request(): void
    {
        $request = $this->createRequestStub([
            'method' => 'GET',
            'get' => [],
        ]);

        $route = [
            'operation' => [
                'responses' => [
                    200 => ['description' => 'Success'],
                ],
            ],
            'method' => 'get',
        ];

        $this->authenticator->expects($this->once())
            ->method('authenticate')
            ->willReturn(['authenticated' => true]);

        $this->validator->expects($this->once())
            ->method('validate')
            ->willReturn(['valid' => true, 'errors' => []]);

        $this->responseGenerator->expects($this->once())
            ->method('generate')
            ->willReturn([
                'status' => 200,
                'body' => ['success' => true],
                'headers' => [],
            ]);

        $result = $this->handler->handle($request, $route);

        $this->assertEquals(200, $result['status']);
        $this->assertEquals(['success' => true], $result['body']);
    }

    public function test_handles_authentication_failure(): void
    {
        $request = $this->createRequestStub([]);

        $route = [
            'operation' => [
                'security' => [['bearerAuth' => []]],
            ],
        ];

        $this->authenticator->expects($this->once())
            ->method('authenticate')
            ->willReturn([
                'authenticated' => false,
                'message' => 'Invalid token',
            ]);

        $this->validator->expects($this->never())
            ->method('validate');

        $this->responseGenerator->expects($this->never())
            ->method('generate');

        $result = $this->handler->handle($request, $route);

        $this->assertEquals(401, $result['status']);
        $this->assertEquals('Unauthorized', $result['body']['error']);
        $this->assertEquals('Invalid token', $result['body']['message']);
    }

    public function test_handles_validation_failure(): void
    {
        $request = $this->createRequestStub([
            'method' => 'POST',
            'rawBody' => '{"name": ""}',
            'headers' => ['content-type' => 'application/json'],
            'get' => [],
        ]);

        $route = [
            'operation' => [
                'requestBody' => [
                    'required' => true,
                ],
            ],
            'method' => 'post',
        ];

        $this->authenticator->expects($this->once())
            ->method('authenticate')
            ->willReturn(['authenticated' => true]);

        $this->validator->expects($this->once())
            ->method('validate')
            ->willReturn([
                'valid' => false,
                'errors' => [
                    'name' => ['The name field is required.'],
                ],
            ]);

        $this->responseGenerator->expects($this->never())
            ->method('generate');

        $result = $this->handler->handle($request, $route);

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('The given data was invalid.', $result['body']['message']);
        $this->assertArrayHasKey('name', $result['body']['errors']);
    }

    public function test_handles_request_with_query_parameters(): void
    {
        $request = $this->createRequestStub([
            'method' => 'GET',
            'get' => ['page' => '2', 'limit' => '10'],
        ]);

        $route = [
            'operation' => [
                'parameters' => [
                    ['name' => 'page', 'in' => 'query'],
                    ['name' => 'limit', 'in' => 'query'],
                ],
            ],
            'method' => 'get',
        ];

        $this->authenticator->expects($this->once())
            ->method('authenticate')
            ->willReturn(['authenticated' => true]);

        $this->validator->expects($this->once())
            ->method('validate')
            ->with(
                $route['operation'],
                null,
                ['page' => '2', 'limit' => '10'],
                []
            )
            ->willReturn(['valid' => true, 'errors' => []]);

        $this->responseGenerator->expects($this->once())
            ->method('generate')
            ->willReturn([
                'status' => 200,
                'body' => ['data' => []],
                'headers' => [],
            ]);

        $result = $this->handler->handle($request, $route);

        $this->assertEquals(200, $result['status']);
    }

    public function test_handles_request_with_path_parameters(): void
    {
        $request = $this->createRequestStub([
            'method' => 'GET',
            'get' => [],
        ]);

        $route = [
            'operation' => [
                'parameters' => [
                    ['name' => 'id', 'in' => 'path'],
                ],
            ],
            'method' => 'get',
            'params' => ['id' => '123'],
        ];

        $this->authenticator->expects($this->once())
            ->method('authenticate')
            ->willReturn(['authenticated' => true]);

        $this->validator->expects($this->once())
            ->method('validate')
            ->with(
                $route['operation'],
                null,
                [],
                ['id' => '123']
            )
            ->willReturn(['valid' => true, 'errors' => []]);

        $this->responseGenerator->expects($this->once())
            ->method('generate')
            ->willReturn([
                'status' => 200,
                'body' => ['id' => 123],
                'headers' => [],
            ]);

        $result = $this->handler->handle($request, $route);

        $this->assertEquals(200, $result['status']);
    }

    public function test_handles_scenario_based_response(): void
    {
        $request = $this->createRequestStub([
            'method' => 'GET',
            'get' => ['_scenario' => 'error'],
        ]);

        $route = [
            'operation' => [],
            'method' => 'get',
        ];

        $this->authenticator->expects($this->once())
            ->method('authenticate')
            ->willReturn(['authenticated' => true]);

        $this->validator->expects($this->once())
            ->method('validate')
            ->willReturn(['valid' => true, 'errors' => []]);

        $this->responseGenerator->expects($this->once())
            ->method('generate')
            ->with(
                $route['operation'],
                500,
                'error',
                []
            )
            ->willReturn([
                'status' => 500,
                'body' => ['error' => 'Internal Server Error'],
                'headers' => [],
            ]);

        $result = $this->handler->handle($request, $route);

        $this->assertEquals(500, $result['status']);
        $this->assertArrayHasKey('error', $result['body']);
    }

    public function test_handles_post_request_with_json_body(): void
    {
        $requestBody = ['name' => 'John Doe', 'email' => 'john@example.com'];

        $request = $this->createRequestStub([
            'method' => 'POST',
            'rawBody' => json_encode($requestBody),
            'headers' => ['content-type' => 'application/json'],
            'get' => [],
        ]);

        $route = [
            'operation' => [
                'requestBody' => [
                    'required' => true,
                ],
            ],
            'method' => 'post',
        ];

        $this->authenticator->expects($this->once())
            ->method('authenticate')
            ->willReturn(['authenticated' => true]);

        $this->validator->expects($this->once())
            ->method('validate')
            ->with(
                $route['operation'],
                $requestBody,
                [],
                []
            )
            ->willReturn(['valid' => true, 'errors' => []]);

        $this->responseGenerator->expects($this->once())
            ->method('generate')
            ->willReturn([
                'status' => 201,
                'body' => ['id' => 1, 'name' => 'John Doe'],
                'headers' => [],
            ]);

        $result = $this->handler->handle($request, $route);

        $this->assertEquals(201, $result['status']);
    }

    public function test_handles_multipart_form_data(): void
    {
        $request = $this->createRequestStub([
            'method' => 'POST',
            'headers' => ['content-type' => 'multipart/form-data'],
            'post' => ['name' => 'John', 'file' => 'uploaded.jpg'],
            'get' => [],
        ]);

        $route = [
            'operation' => [],
            'method' => 'post',
        ];

        $this->authenticator->expects($this->once())
            ->method('authenticate')
            ->willReturn(['authenticated' => true]);

        $this->validator->expects($this->once())
            ->method('validate')
            ->with(
                $route['operation'],
                ['name' => 'John', 'file' => 'uploaded.jpg'],
                [],
                []
            )
            ->willReturn(['valid' => true, 'errors' => []]);

        $this->responseGenerator->expects($this->once())
            ->method('generate')
            ->willReturn([
                'status' => 201,
                'body' => ['success' => true],
                'headers' => [],
            ]);

        $result = $this->handler->handle($request, $route);

        $this->assertEquals(201, $result['status']);
    }
}
