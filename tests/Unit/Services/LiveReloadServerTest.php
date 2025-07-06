<?php

namespace LaravelPrism\Tests\Unit\Services;

use LaravelPrism\Services\LiveReloadServer;
use Orchestra\Testbench\TestCase;
use Ratchet\ConnectionInterface;
use React\EventLoop\Loop;

class LiveReloadServerTest extends TestCase
{
    private LiveReloadServer $server;

    protected function setUp(): void
    {
        parent::setUp();
        $this->server = new LiveReloadServer;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Loop::stop();
    }

    public function test_can_instantiate_server(): void
    {
        $this->assertInstanceOf(LiveReloadServer::class, $this->server);
    }

    public function test_accepts_websocket_connections(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);

        // Capture output
        ob_start();
        $this->server->onOpen($connection);
        $output = ob_get_clean();

        $this->assertStringContainsString('New connection', $output);
        $resourceId = spl_object_id($connection);
        $this->assertStringContainsString((string)$resourceId, $output);
    }

    public function test_handles_connection_close(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);

        // First open the connection
        ob_start();
        $this->server->onOpen($connection);
        ob_end_clean();

        // Then close it
        ob_start();
        $this->server->onClose($connection);
        $output = ob_get_clean();

        $resourceId = spl_object_id($connection);
        $this->assertStringContainsString("Connection {$resourceId} has disconnected", $output);
    }

    public function test_handles_connection_error(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())->method('close');

        $exception = new \Exception('Test error');

        ob_start();
        $this->server->onError($connection, $exception);
        $output = ob_get_clean();

        $this->assertStringContainsString('An error has occurred', $output);
        $this->assertStringContainsString('Test error', $output);
    }

    public function test_notifies_all_connected_clients(): void
    {
        $connection1 = $this->createMock(ConnectionInterface::class);
        $connection1->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($data) {
                $decoded = json_decode($data, true);

                return $decoded['event'] === 'documentation-updated' &&
                       $decoded['path'] === '/test/path.php';
            }));

        $connection2 = $this->createMock(ConnectionInterface::class);
        $connection2->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($data) {
                $decoded = json_decode($data, true);

                return $decoded['event'] === 'documentation-updated' &&
                       $decoded['path'] === '/test/path.php';
            }));

        // Open connections
        ob_start();
        $this->server->onOpen($connection1);
        $this->server->onOpen($connection2);
        ob_end_clean();

        // Notify clients
        $this->server->notifyClients([
            'event' => 'documentation-updated',
            'path' => '/test/path.php',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function test_http_response_methods(): void
    {
        // Test getSwaggerUIResponse
        $reflection = new \ReflectionClass($this->server);
        $method = $reflection->getMethod('getSwaggerUIResponse');
        $method->setAccessible(true);

        // We'll just check that it returns a valid HTTP response format
        $response = $method->invoke($this->server);
        $this->assertStringContainsString('HTTP/1.1 200 OK', $response);
        $this->assertStringContainsString('Content-Type: text/html', $response);

        // Test getOpenApiResponse
        $method = $reflection->getMethod('getOpenApiResponse');
        $method->setAccessible(true);

        // Create a test OpenAPI file
        $openApiPath = storage_path('app/prism/openapi.json');
        if (! is_dir(dirname($openApiPath))) {
            mkdir(dirname($openApiPath), 0777, true);
        }
        file_put_contents($openApiPath, json_encode(['openapi' => '3.0.0']));

        $response = $method->invoke($this->server);
        $this->assertStringContainsString('HTTP/1.1 200 OK', $response);
        $this->assertStringContainsString('Content-Type: application/json', $response);
        $this->assertStringContainsString('Access-Control-Allow-Origin: *', $response);

        // Clean up
        unlink($openApiPath);
    }

    public function test_parse_http_request(): void
    {
        $reflection = new \ReflectionClass($this->server);
        $method = $reflection->getMethod('parseHttpRequest');
        $method->setAccessible(true);

        $httpRequest = "GET /test HTTP/1.1\r\nHost: localhost\r\n\r\n";
        $parsed = $method->invoke($this->server, $httpRequest);

        $this->assertEquals('GET', $parsed['method']);
        $this->assertEquals('/test', $parsed['path']);
        $this->assertEquals('localhost', $parsed['headers']['Host']);
    }

    public function test_404_response(): void
    {
        $reflection = new \ReflectionClass($this->server);
        $method = $reflection->getMethod('get404Response');
        $method->setAccessible(true);

        $response = $method->invoke($this->server);
        $this->assertStringContainsString('HTTP/1.1 404 Not Found', $response);
    }
}
