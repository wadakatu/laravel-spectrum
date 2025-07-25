<?php

namespace LaravelSpectrum\MockServer;

use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Workerman\Worker;

class MockServer
{
    private Worker $worker;

    private array $openapi;

    private RequestHandler $requestHandler;

    private RouteResolver $routeResolver;

    private int $port;

    private string $host;

    public function __construct(
        array $openapi,
        RequestHandler $requestHandler,
        RouteResolver $routeResolver,
        string $host = '127.0.0.1',
        int $port = 8081
    ) {
        $this->openapi = $openapi;
        $this->requestHandler = $requestHandler;
        $this->routeResolver = $routeResolver;
        $this->host = $host;
        $this->port = $port;
    }

    public function start(): void
    {
        // Set up Workerman command line arguments
        global $argv;
        if (! isset($argv[1])) {
            $argv = ['spectrum:mock', 'start'];
        }

        $this->worker = new Worker("http://{$this->host}:{$this->port}");
        $this->worker->count = 1;
        $this->worker->name = 'Laravel-Spectrum-Mock-Server';

        $this->worker->onMessage = [$this, 'handleRequest'];

        // CORS対応
        $this->worker->onWorkerStart = function ($worker) {
            echo "\n🚀 Mock Server started at http://{$this->host}:{$this->port}\n";
            echo '📚 Serving '.count($this->openapi['paths'] ?? [])." endpoints\n";
            echo "🛑 Press Ctrl+C to stop\n\n";
        };

        Worker::runAll();
    }

    public function handleRequest(TcpConnection $connection, Request $request): void
    {
        $path = parse_url($request->uri(), PHP_URL_PATH);
        $method = strtolower($request->method());

        // ログ出力
        $this->logRequest($method, $path);

        // CORS preflight
        if ($method === 'options') {
            $this->sendCorsResponse($connection);

            return;
        }

        try {
            // ルート解決
            $route = $this->routeResolver->resolve($path, $method, $this->openapi);

            if (! $route) {
                $this->send404($connection, $path);

                return;
            }

            // リクエスト処理
            $response = $this->requestHandler->handle($request, $route);

            // レスポンス送信
            $this->sendResponse($connection, $response);

        } catch (\Exception $e) {
            $this->sendError($connection, $e);
        }
    }

    private function sendResponse(TcpConnection $connection, array $response): void
    {
        $headers = array_merge([
            'Content-Type' => 'application/json',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With',
            'X-Powered-By' => 'Laravel Spectrum Mock Server',
        ], $response['headers'] ?? []);

        $connection->send(new Response(
            $response['status'] ?? 200,
            $headers,
            json_encode($response['body'], JSON_PRETTY_PRINT)
        ));
    }

    private function send404(TcpConnection $connection, string $path): void
    {
        $this->sendResponse($connection, [
            'status' => 404,
            'body' => [
                'error' => 'Not Found',
                'message' => "The requested path '{$path}' was not found.",
                'path' => $path,
            ],
        ]);
    }

    private function sendError(TcpConnection $connection, \Exception $e): void
    {
        $status = $e->getCode() ?: 500;
        if ($status < 100 || $status > 599) {
            $status = 500;
        }

        $this->sendResponse($connection, [
            'status' => $status,
            'body' => [
                'error' => 'Server Error',
                'message' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTrace() : null,
            ],
        ]);
    }

    private function sendCorsResponse(TcpConnection $connection): void
    {
        $connection->send(new Response(204, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With',
            'Access-Control-Max-Age' => '86400',
        ]));
    }

    private function logRequest(string $method, string $path): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $methodColored = $this->colorizeMethod($method);
        echo "[{$timestamp}] {$methodColored} {$path}\n";
    }

    private function colorizeMethod(string $method): string
    {
        $colors = [
            'get' => "\033[32m",    // Green
            'post' => "\033[33m",   // Yellow
            'put' => "\033[34m",    // Blue
            'patch' => "\033[35m",  // Magenta
            'delete' => "\033[31m", // Red
        ];

        $color = $colors[$method] ?? "\033[37m"; // Default white

        return $color.strtoupper($method)."\033[0m";
    }
}
