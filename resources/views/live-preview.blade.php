<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('prism.title', 'API Documentation') }} - Live Preview</title>
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
        const wsPort = parseInt('{{ $wsPort ?? 8081 }}');
        
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
        const ws = new WebSocket(`ws://localhost:${wsPort}`);
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
        
        ws.onerror = (error) => {
            console.error('WebSocket error:', error);
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