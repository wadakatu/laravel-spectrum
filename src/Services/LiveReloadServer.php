<?php

namespace LaravelPrism\Services;

use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Workerman\Worker;

class LiveReloadServer
{
    protected $clients;

    protected $httpWorker;

    protected $wsWorker;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
    }

    public function start(string $host, int $port): void
    {
        // Start HTTP server for Swagger UI
        $this->startHttpServer($host, $port);

        // Start WebSocket server for Live Reload
        $this->startWebSocketServer($host, $port + 1);

        // Run all workers
        Worker::runAll();
    }

    private function startHttpServer(string $host, int $port): void
    {
        $this->httpWorker = new Worker("http://{$host}:{$port}");
        $this->httpWorker->count = 1;

        $this->httpWorker->onMessage = function (TcpConnection $connection, Request $request) {
            $path = $request->path();

            if ($path === '/') {
                $connection->send(new Response(200, [
                    'Content-Type' => 'text/html; charset=utf-8',
                ], $this->getSwaggerUIHtml()));
            } elseif ($path === '/openapi.json') {
                $jsonPath = storage_path('app/prism/openapi.json');
                $json = file_exists($jsonPath) ? file_get_contents($jsonPath) : '{}';

                $connection->send(new Response(200, [
                    'Content-Type' => 'application/json',
                    'Access-Control-Allow-Origin' => '*',
                ], $json));
            } else {
                $connection->send(new Response(404, [], '404 Not Found'));
            }
        };
    }

    private function startWebSocketServer(string $host, int $port): void
    {
        $this->wsWorker = new Worker("websocket://{$host}:{$port}");
        $this->wsWorker->count = 1;

        $this->wsWorker->onConnect = function (TcpConnection $connection) {
            $this->clients->attach($connection);
            $resourceId = spl_object_id($connection);
            echo "New connection! ({$resourceId})\n";
        };

        $this->wsWorker->onMessage = function (TcpConnection $connection, $data) {
            // Client messages are ignored
        };

        $this->wsWorker->onClose = function (TcpConnection $connection) {
            $this->clients->detach($connection);
            $resourceId = spl_object_id($connection);
            echo "Connection {$resourceId} has disconnected\n";
        };

        $this->wsWorker->onError = function (TcpConnection $connection, $code, $msg) {
            echo "Error: {$msg}\n";
        };
    }

    public function notifyClients(array $data): void
    {
        $message = json_encode($data);

        foreach ($this->clients as $client) {
            $client->send($message);
        }
    }

    private function getSwaggerUIHtml(): string
    {
        // Check if we're in a Laravel app with view support
        if (function_exists('view') && view()->exists('prism::live-preview')) {
            return view('prism::live-preview', ['wsPort' => 8081])->render();
        }

        // Fallback HTML for testing
        return <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laravel Prism - Live Preview</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css">
    <style>
        .live-indicator {
            position: fixed;
            top: 10px;
            right: 10px;
            padding: 5px 10px;
            background: #4CAF50;
            color: white;
            border-radius: 4px;
            font-size: 12px;
            z-index: 1000;
        }
        
        .live-indicator.disconnected {
            background: #f44336;
        }
        
        .update-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 15px 20px;
            background: #2196F3;
            color: white;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            opacity: 0;
            transform: translateY(100px);
            transition: all 0.3s ease;
        }
        
        .update-notification.show {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <div class="live-indicator" id="live-indicator">
        🔴 LIVE
    </div>
    
    <div class="update-notification" id="update-notification">
        📝 Documentation updated!
    </div>
    
    <div id="swagger-ui"></div>

    <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-standalone-preset.js"></script>
    
    <script>
        let ui;
        
        // Initialize Swagger UI
        function initSwaggerUI() {
            ui = SwaggerUIBundle({
                url: "/openapi.json",
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIBundle.SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "StandaloneLayout"
            });
        }
        
        // WebSocket connection
        const ws = new WebSocket('ws://localhost:8081');
        const indicator = document.getElementById('live-indicator');
        const notification = document.getElementById('update-notification');
        
        ws.onopen = () => {
            console.log('Connected to live reload server');
            indicator.textContent = '🟢 LIVE';
            indicator.classList.remove('disconnected');
        };
        
        ws.onclose = () => {
            console.log('Disconnected from live reload server');
            indicator.textContent = '🔴 DISCONNECTED';
            indicator.classList.add('disconnected');
        };
        
        ws.onmessage = (event) => {
            const data = JSON.parse(event.data);
            
            if (data.event === 'documentation-updated') {
                console.log('Documentation updated:', data.path);
                
                // Show notification
                notification.classList.add('show');
                setTimeout(() => {
                    notification.classList.remove('show');
                }, 3000);
                
                // Reload Swagger UI
                setTimeout(() => {
                    ui.specActions.download();
                }, 500);
            }
        };
        
        // Initialize
        initSwaggerUI();
    </script>
</body>
</html>
HTML;
    }
}
