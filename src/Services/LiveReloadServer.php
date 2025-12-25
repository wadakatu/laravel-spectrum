<?php

namespace LaravelSpectrum\Services;

use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Workerman\Worker;

class LiveReloadServer
{
    protected static ?\SplObjectStorage $clients = null;

    protected ?Worker $httpWorker = null;

    protected ?Worker $wsWorker = null;

    protected static ?self $instance = null;

    public function __construct()
    {
        if (! self::$clients) {
            self::$clients = new \SplObjectStorage;
        }
        self::$instance = $this;
    }

    /**
     * Reset clients for testing purposes
     */
    public static function resetClients(): void
    {
        self::$clients = new \SplObjectStorage;
    }

    public function start(string $host, int $port): void
    {
        // Define global websocket clients storage
        global $wsClients;
        $wsClients = new \SplObjectStorage;

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
                // パッケージ開発環境と通常環境の両方に対応
                $possiblePaths = [];

                if (function_exists('storage_path')) {
                    $possiblePaths[] = storage_path('app/spectrum/openapi.json');
                }

                // パッケージ開発環境用のパス
                $possiblePaths[] = getcwd().'/storage/spectrum/openapi.json';

                $json = '{}';
                $lastModified = 0;
                foreach ($possiblePaths as $jsonPath) {
                    if (file_exists($jsonPath)) {
                        // ファイルシステムキャッシュをクリア
                        clearstatcache(true, $jsonPath);

                        // 最新のファイル内容を読み込む
                        $json = file_get_contents($jsonPath);
                        $lastModified = filemtime($jsonPath);

                        // デバッグ情報
                        error_log("LiveReloadServer: Serving {$jsonPath} (modified: ".date('Y-m-d H:i:s', $lastModified).', size: '.strlen($json).' bytes)');
                        break;
                    }
                }

                // キャッシュを完全に無効化するヘッダー
                $headers = [
                    'Content-Type' => 'application/json',
                    'Access-Control-Allow-Origin' => '*',
                    'Cache-Control' => 'no-cache, no-store, must-revalidate',
                    'Pragma' => 'no-cache',
                    'Expires' => '0',
                    'X-Content-Type-Options' => 'nosniff',
                ];

                // ETagとLast-Modifiedヘッダーを追加
                if ($lastModified > 0) {
                    $etag = md5($json);
                    $headers['ETag'] = '"'.$etag.'"';
                    $headers['Last-Modified'] = gmdate('D, d M Y H:i:s', $lastModified).' GMT';
                }

                $connection->send(new Response(200, $headers, $json));
            } else {
                $connection->send(new Response(404, [], '404 Not Found'));
            }
        };
    }

    private function startWebSocketServer(string $host, int $port): void
    {
        global $wsClients;

        $this->wsWorker = new Worker("websocket://{$host}:{$port}");
        $this->wsWorker->count = 1;
        $this->wsWorker->name = 'WebSocket-Server';

        // Store wsClients reference in worker for access in callbacks
        // @phpstan-ignore-next-line
        $this->wsWorker->wsClients = &$wsClients;

        // Set up timer to check for messages
        $this->wsWorker->onWorkerStart = function ($worker) use (&$wsClients) {
            // Check for messages every 100ms
            \Workerman\Timer::add(0.1, function () use (&$wsClients) {
                $tempFile = sys_get_temp_dir().'/spectrum_ws_message.json';

                if (file_exists($tempFile)) {
                    $messages = file($tempFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

                    if (! empty($messages)) {
                        // Clear the file
                        file_put_contents($tempFile, '');

                        foreach ($messages as $message) {
                            echo "[WebSocket Worker] Broadcasting message: {$message}\n";
                            $clientCount = count($wsClients);
                            echo "[WebSocket Worker] Sending to {$clientCount} clients\n";

                            foreach ($wsClients as $client) {
                                try {
                                    $client->send($message);
                                    echo "[WebSocket Worker] Message sent to client\n";
                                } catch (\Exception $e) {
                                    echo "[WebSocket Worker] Failed to send: {$e->getMessage()}\n";
                                }
                            }
                        }
                    }
                }
            });
        };

        $this->wsWorker->onConnect = function (TcpConnection $connection) use (&$wsClients) {
            $wsClients->attach($connection);
            self::$clients->attach($connection);
            $resourceId = spl_object_id($connection);
            $clientCount = count($wsClients);
            echo "New connection! ({$resourceId}) - Total clients: {$clientCount}\n";
        };

        $this->wsWorker->onMessage = function (TcpConnection $connection, $data) {
            // Client messages are ignored
        };

        $this->wsWorker->onClose = function (TcpConnection $connection) use (&$wsClients) {
            $wsClients->detach($connection);
            self::$clients->detach($connection);
            $resourceId = spl_object_id($connection);
            $clientCount = count($wsClients);
            echo "Connection {$resourceId} has disconnected - Remaining clients: {$clientCount}\n";
        };

        $this->wsWorker->onError = function (TcpConnection $connection, $code, $msg) {
            echo "Error: {$msg}\n";
        };
    }

    public function notifyClients(array $data): void
    {
        $message = json_encode($data);
        echo "[LiveReloadServer] Writing notification to file: {$message}\n";

        // Write message to a temporary file for WebSocket worker to read
        $tempFile = sys_get_temp_dir().'/spectrum_ws_message.json';
        file_put_contents($tempFile, $message."\n", FILE_APPEND | LOCK_EX);

        echo "[LiveReloadServer] Notification written to {$tempFile}\n";
    }

    private function getSwaggerUIHtml(): string
    {
        // Check if we're in a Laravel app with view support
        if (function_exists('view') && view()->exists('spectrum::live-preview')) {
            // Extract port from the HTTP worker address
            $wsPort = 8081; // Default
            if ($this->httpWorker) {
                $address = $this->httpWorker->getSocketName();
                if (preg_match('/:(\\d+)$/', $address, $matches)) {
                    $wsPort = (int) $matches[1] + 1;
                }
            }

            return view('spectrum::live-preview', ['wsPort' => $wsPort])->render();
        }

        // Fallback HTML for testing
        return <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laravel Spectrum - Live Preview</title>
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
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ]
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
                
                // 全てのファイル変更時に自動リロード
                console.log('File changed:', data.path);
                console.log('Timestamp:', data.timestamp);
                notification.textContent = '🔄 Reloading page...';
                
                setTimeout(() => {
                    // 強制的にキャッシュを無視してリロード
                    const url = new URL(window.location.href);
                    url.search = ''; // Clear all query parameters
                    url.searchParams.set('t', new Date().getTime().toString());
                    window.location.href = url.href;
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
