<?php

namespace LaravelSpectrum\Tests\Unit\Services;

use LaravelSpectrum\Services\LiveReloadServer;
use Orchestra\Testbench\TestCase;

class LiveReloadServerTest extends TestCase
{
    private LiveReloadServer $server;

    protected function setUp(): void
    {
        parent::setUp();
        LiveReloadServer::resetClients();
        $this->server = new LiveReloadServer;
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
