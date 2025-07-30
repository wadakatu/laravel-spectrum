<?php

namespace LaravelSpectrum\Tests\Unit\Services;

use LaravelSpectrum\Services\LiveReloadServer;
use Orchestra\Testbench\TestCase;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

class LiveReloadServerTest extends TestCase
{
    private LiveReloadServer $server;

    protected function setUp(): void
    {
        parent::setUp();
        LiveReloadServer::resetClients();
        $this->server = new LiveReloadServer;
    }

    /**
     * Create a stub Request object with the given configuration
     */
    private function createRequestStub(array $config = []): Request
    {
        return new class($config) extends Request {
            private array $config;

            public function __construct(array $config)
            {
                $this->config = $config;
            }

            public function path(): string
            {
                return $this->config['path'] ?? '/';
            }

            public function method(): string
            {
                return $this->config['method'] ?? 'GET';
            }

            public function header(?string $name = null, mixed $default = null): mixed
            {
                if ($name === null) {
                    return $this->config['headers'] ?? [];
                }
                return ($this->config['headers'] ?? [])[strtolower($name)] ?? $default;
            }
        };
    }

    public function test_can_instantiate_server(): void
    {
        $this->assertInstanceOf(LiveReloadServer::class, $this->server);
    }

    public function test_notify_clients_with_no_clients(): void
    {
        // Clean up any existing message file
        $tempFile = sys_get_temp_dir().'/spectrum_ws_message.json';
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }

        // Test that notifyClients doesn't throw error when no clients are connected
        $this->server->notifyClients([
            'event' => 'test-event',
            'data' => 'test-data',
        ]);

        // Verify file was created
        $this->assertFileExists($tempFile);

        // Clean up
        unlink($tempFile);
    }

    public function test_notify_clients_encodes_json_properly(): void
    {
        // Clean up any existing message file
        $tempFile = sys_get_temp_dir().'/spectrum_ws_message.json';
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }

        // Test notification
        $this->server->notifyClients([
            'event' => 'documentation-updated',
            'path' => '/test/path.php',
            'timestamp' => '2023-01-01T00:00:00Z',
        ]);

        // Verify the message was written to file
        $this->assertFileExists($tempFile);

        // Read and verify the message content
        $content = file_get_contents($tempFile);
        $messages = explode("\n", trim($content));
        $this->assertCount(1, $messages);

        $decoded = json_decode($messages[0], true);
        $this->assertEquals('documentation-updated', $decoded['event']);
        $this->assertEquals('/test/path.php', $decoded['path']);
        $this->assertEquals('2023-01-01T00:00:00Z', $decoded['timestamp']);

        // Clean up
        unlink($tempFile);
    }

    public function test_swagger_ui_html_generation(): void
    {
        // Use reflection to test the private method
        $reflection = new \ReflectionClass($this->server);
        $method = $reflection->getMethod('getSwaggerUIHtml');
        $method->setAccessible(true);

        $html = $method->invoke($this->server);

        // Test that HTML contains necessary elements
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<title>Laravel Spectrum - Live Preview</title>', $html);
        $this->assertStringContainsString('swagger-ui', $html);
        $this->assertStringContainsString('WebSocket', $html);
        $this->assertStringContainsString('ws://localhost:8081', $html);
        $this->assertStringContainsString('live-indicator', $html);
        $this->assertStringContainsString('update-notification', $html);
    }

    public function test_swagger_ui_html_uses_fallback_when_view_not_available(): void
    {
        // Since we're testing outside of a full Laravel app,
        // the view function won't exist or won't find the view,
        // so we'll get the fallback HTML

        $reflection = new \ReflectionClass($this->server);
        $method = $reflection->getMethod('getSwaggerUIHtml');
        $method->setAccessible(true);

        $html = $method->invoke($this->server);

        // Verify fallback HTML is returned
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<title>Laravel Spectrum - Live Preview</title>', $html);
        $this->assertStringContainsString('swagger-ui', $html);
    }

    public function test_start_method_creates_workers(): void
    {
        // リフレクションを使ってプライベートメソッドにアクセス
        $reflection = new \ReflectionClass($this->server);

        // startHttpServerメソッドを取得
        $startHttpServerMethod = $reflection->getMethod('startHttpServer');
        $startHttpServerMethod->setAccessible(true);

        // startWebSocketServerメソッドを取得
        $startWebSocketServerMethod = $reflection->getMethod('startWebSocketServer');
        $startWebSocketServerMethod->setAccessible(true);

        // HTTPワーカーの作成をテスト
        $startHttpServerMethod->invoke($this->server, 'localhost', 8080);

        $httpWorkerProperty = $reflection->getProperty('httpWorker');
        $httpWorkerProperty->setAccessible(true);
        $httpWorker = $httpWorkerProperty->getValue($this->server);

        $this->assertNotNull($httpWorker);
        // Workerのnameプロパティはデフォルトで'none'
        $this->assertEquals('none', $httpWorker->name);

        // WebSocketワーカーの作成をテスト
        $startWebSocketServerMethod->invoke($this->server, 'localhost', 8081);

        $wsWorkerProperty = $reflection->getProperty('wsWorker');
        $wsWorkerProperty->setAccessible(true);
        $wsWorker = $wsWorkerProperty->getValue($this->server);

        $this->assertNotNull($wsWorker);
        $this->assertEquals('WebSocket-Server', $wsWorker->name);
    }

    public function test_http_server_handles_root_request(): void
    {
        $reflection = new \ReflectionClass($this->server);
        $startHttpServerMethod = $reflection->getMethod('startHttpServer');
        $startHttpServerMethod->setAccessible(true);

        // HTTPサーバーを起動
        $startHttpServerMethod->invoke($this->server, 'localhost', 8080);

        $httpWorkerProperty = $reflection->getProperty('httpWorker');
        $httpWorkerProperty->setAccessible(true);
        $httpWorker = $httpWorkerProperty->getValue($this->server);

        // モックのリクエストとコネクションを作成
        $mockRequest = $this->createRequestStub([
            'path' => '/',
        ]);

        $mockConnection = $this->createMock(TcpConnection::class);

        // レスポンスが送信されることを期待
        $mockConnection->expects($this->once())
            ->method('send')
            ->with($this->isInstanceOf(Response::class));

        // onMessageコールバックを実行
        $callback = $httpWorker->onMessage;
        $callback($mockConnection, $mockRequest);
    }

    public function test_http_server_handles_openapi_json_request(): void
    {
        $reflection = new \ReflectionClass($this->server);
        $startHttpServerMethod = $reflection->getMethod('startHttpServer');
        $startHttpServerMethod->setAccessible(true);

        // テスト用のOpenAPIファイルを作成
        $testDir = sys_get_temp_dir().'/spectrum_test';
        $testFile = $testDir.'/openapi.json';
        @mkdir($testDir, 0777, true);
        file_put_contents($testFile, '{"openapi": "3.0.0"}');

        // HTTPサーバーを起動
        $startHttpServerMethod->invoke($this->server, 'localhost', 8080);

        $httpWorkerProperty = $reflection->getProperty('httpWorker');
        $httpWorkerProperty->setAccessible(true);
        $httpWorker = $httpWorkerProperty->getValue($this->server);

        // モックのリクエストとコネクションを作成
        $mockRequest = $this->createRequestStub([
            'path' => '/openapi.json',
        ]);

        $mockConnection = $this->createMock(TcpConnection::class);

        // レスポンスが送信されることを期待
        $mockConnection->expects($this->once())
            ->method('send')
            ->with($this->isInstanceOf(Response::class));

        // onMessageコールバックを実行
        $callback = $httpWorker->onMessage;
        $callback($mockConnection, $mockRequest);

        // クリーンアップ
        @unlink($testFile);
        @rmdir($testDir);
    }

    public function test_http_server_handles_unknown_path(): void
    {
        $reflection = new \ReflectionClass($this->server);
        $startHttpServerMethod = $reflection->getMethod('startHttpServer');
        $startHttpServerMethod->setAccessible(true);

        // HTTPサーバーを起動
        $startHttpServerMethod->invoke($this->server, 'localhost', 8080);

        $httpWorkerProperty = $reflection->getProperty('httpWorker');
        $httpWorkerProperty->setAccessible(true);
        $httpWorker = $httpWorkerProperty->getValue($this->server);

        // モックのリクエストとコネクションを作成
        $mockRequest = $this->createRequestStub([
            'path' => '/unknown',
        ]);

        $mockConnection = $this->createMock(TcpConnection::class);

        // 404レスポンスが送信されることを期待
        $mockConnection->expects($this->once())
            ->method('send')
            ->with($this->isInstanceOf(Response::class));

        // onMessageコールバックを実行
        $callback = $httpWorker->onMessage;
        $callback($mockConnection, $mockRequest);
    }

    public function test_websocket_server_initialization(): void
    {
        $reflection = new \ReflectionClass($this->server);
        $startWebSocketServerMethod = $reflection->getMethod('startWebSocketServer');
        $startWebSocketServerMethod->setAccessible(true);

        // WebSocketサーバーを起動
        $startWebSocketServerMethod->invoke($this->server, 'localhost', 8081);

        $wsWorkerProperty = $reflection->getProperty('wsWorker');
        $wsWorkerProperty->setAccessible(true);
        $wsWorker = $wsWorkerProperty->getValue($this->server);

        // WebSocketワーカーが正しく作成されていることを確認
        $this->assertNotNull($wsWorker);
        $this->assertEquals('WebSocket-Server', $wsWorker->name);
        $this->assertEquals(1, $wsWorker->count);

        // コールバックが設定されていることを確認
        $this->assertNotNull($wsWorker->onWorkerStart);
        $this->assertNotNull($wsWorker->onConnect);
        $this->assertNotNull($wsWorker->onMessage);
        $this->assertNotNull($wsWorker->onClose);
    }

    public function test_websocket_client_connection(): void
    {
        global $wsClients;
        $wsClients = new \SplObjectStorage;

        $reflection = new \ReflectionClass($this->server);
        $startWebSocketServerMethod = $reflection->getMethod('startWebSocketServer');
        $startWebSocketServerMethod->setAccessible(true);

        // WebSocketサーバーを起動
        $startWebSocketServerMethod->invoke($this->server, 'localhost', 8081);

        $wsWorkerProperty = $reflection->getProperty('wsWorker');
        $wsWorkerProperty->setAccessible(true);
        $wsWorker = $wsWorkerProperty->getValue($this->server);

        // モックのコネクションを作成
        $mockConnection = $this->createMock(TcpConnection::class);
        $mockConnection->id = 123;

        // onConnectコールバックを実行
        $onConnect = $wsWorker->onConnect;
        $onConnect($mockConnection);

        // クライアントが追加されたことを確認
        $this->assertTrue($wsClients->contains($mockConnection));
    }

    public function test_websocket_client_disconnection(): void
    {
        global $wsClients;
        $wsClients = new \SplObjectStorage;

        $reflection = new \ReflectionClass($this->server);
        $startWebSocketServerMethod = $reflection->getMethod('startWebSocketServer');
        $startWebSocketServerMethod->setAccessible(true);

        // WebSocketサーバーを起動
        $startWebSocketServerMethod->invoke($this->server, 'localhost', 8081);

        $wsWorkerProperty = $reflection->getProperty('wsWorker');
        $wsWorkerProperty->setAccessible(true);
        $wsWorker = $wsWorkerProperty->getValue($this->server);

        // モックのコネクションを作成
        $mockConnection = $this->createMock(TcpConnection::class);
        $mockConnection->id = 456;

        // まずクライアントを接続
        $wsClients->attach($mockConnection);
        $this->assertTrue($wsClients->contains($mockConnection));

        // onCloseコールバックを実行
        $onClose = $wsWorker->onClose;
        $onClose($mockConnection);

        // クライアントが削除されたことを確認
        $this->assertFalse($wsClients->contains($mockConnection));
    }

    public function test_websocket_message_handling(): void
    {
        global $wsClients;
        $wsClients = new \SplObjectStorage;

        $reflection = new \ReflectionClass($this->server);
        $startWebSocketServerMethod = $reflection->getMethod('startWebSocketServer');
        $startWebSocketServerMethod->setAccessible(true);

        // WebSocketサーバーを起動
        $startWebSocketServerMethod->invoke($this->server, 'localhost', 8081);

        $wsWorkerProperty = $reflection->getProperty('wsWorker');
        $wsWorkerProperty->setAccessible(true);
        $wsWorker = $wsWorkerProperty->getValue($this->server);

        // モックのコネクションを作成
        $mockConnection = $this->createMock(TcpConnection::class);

        // メッセージは無視されるので、sendは呼ばれない
        $mockConnection->expects($this->never())
            ->method('send');

        // onMessageコールバックを実行
        $onMessage = $wsWorker->onMessage;
        $onMessage($mockConnection, '{"type":"ping"}');

        // メッセージが処理されないことを確認（コールバックが存在することだけ確認）
        $this->assertNotNull($onMessage);
    }

    public function test_clients_storage_initialization(): void
    {
        $reflection = new \ReflectionClass(LiveReloadServer::class);
        $clientsProperty = $reflection->getProperty('clients');
        $clientsProperty->setAccessible(true);

        $clients = $clientsProperty->getValue(null);

        $this->assertInstanceOf(\SplObjectStorage::class, $clients);
        $this->assertEquals(0, $clients->count());
    }
}
