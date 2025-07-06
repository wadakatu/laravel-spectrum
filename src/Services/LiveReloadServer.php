<?php

namespace LaravelPrism\Services;

use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\MessageComponentInterface;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;
use React\Socket\Server as ReactServer;

class LiveReloadServer implements MessageComponentInterface
{
    protected $clients;


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
    }

    private function startHttpServer(string $host, int $port): void
    {
        $server = new ReactServer("{$host}:{$port}", Loop::get());

        $server->on('connection', function ($connection) {
            $connection->on('data', function ($data) use ($connection) {
                $request = $this->parseHttpRequest($data);

                if ($request['path'] === '/') {
                    $connection->write($this->getSwaggerUIResponse());
                } elseif ($request['path'] === '/openapi.json') {
                    $connection->write($this->getOpenApiResponse());
                } else {
                    $connection->write($this->get404Response());
                }

                $connection->end();
            });
        });
    }

    private function startWebSocketServer(string $host, int $port): void
    {
        $wsServer = new WsServer($this);
        $httpServer = new HttpServer($wsServer);

        IoServer::factory(
            $httpServer,
            $port,
            $host
        );
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn);
        $resourceId = spl_object_id($conn);
        echo "New connection! ({$resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        // Client messages are ignored
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $this->clients->detach($conn);
        $resourceId = spl_object_id($conn);
        echo "Connection {$resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

    public function notifyClients(array $data): void
    {
        $message = json_encode($data);

        foreach ($this->clients as $client) {
            $client->send($message);
        }
    }

    private function getSwaggerUIResponse(): string
    {
        $html = $this->getSwaggerUIHtml();

        $headers = [
            'HTTP/1.1 200 OK',
            'Content-Type: text/html; charset=utf-8',
            'Content-Length: '.strlen($html),
            'Connection: close',
        ];

        return implode("\r\n", $headers)."\r\n\r\n".$html;
    }

    private function getOpenApiResponse(): string
    {
        $jsonPath = storage_path('app/prism/openapi.json');
        $json = file_exists($jsonPath) ? file_get_contents($jsonPath) : '{}';

        $headers = [
            'HTTP/1.1 200 OK',
            'Content-Type: application/json',
            'Content-Length: '.strlen($json),
            'Access-Control-Allow-Origin: *',
            'Connection: close',
        ];

        return implode("\r\n", $headers)."\r\n\r\n".$json;
    }

    private function get404Response(): string
    {
        $body = '404 Not Found';

        $headers = [
            'HTTP/1.1 404 Not Found',
            'Content-Type: text/plain',
            'Content-Length: '.strlen($body),
            'Connection: close',
        ];

        return implode("\r\n", $headers)."\r\n\r\n".$body;
    }

    private function parseHttpRequest(string $data): array
    {
        $lines = explode("\r\n", $data);
        $firstLine = explode(' ', $lines[0]);

        $method = $firstLine[0];
        $path = $firstLine[1] ?? '';

        $headers = [];
        for ($i = 1; $i < count($lines); $i++) {
            if (empty($lines[$i])) {
                break;
            }
            [$key, $value] = explode(': ', $lines[$i], 2);
            $headers[$key] = $value;
        }

        return [
            'method' => $method,
            'path' => $path,
            'headers' => $headers,
        ];
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
