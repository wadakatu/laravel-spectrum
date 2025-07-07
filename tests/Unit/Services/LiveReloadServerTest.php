<?php

namespace LaravelPrism\Tests\Unit\Services;

use LaravelPrism\Services\LiveReloadServer;
use Orchestra\Testbench\TestCase;

class LiveReloadServerTest extends TestCase
{
    private LiveReloadServer $server;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Skip tests that require Workerman runtime
        if (!defined('WORKERMAN_RUN_MODE')) {
            $this->markTestSkipped('LiveReloadServer tests require Workerman runtime');
        }
        
        $this->server = new LiveReloadServer;
    }

    public function test_can_instantiate_server(): void
    {
        $this->assertInstanceOf(LiveReloadServer::class, $this->server);
    }

    public function test_notify_clients_method_exists(): void
    {
        // Just verify the method exists and accepts array
        $this->assertTrue(method_exists($this->server, 'notifyClients'));
        
        // Call it without expecting any output (no clients connected)
        $this->server->notifyClients([
            'event' => 'test',
            'data' => 'test data'
        ]);
        
        $this->assertTrue(true); // If we got here without error, test passes
    }

    public function test_swagger_ui_html_generation(): void
    {
        // Use reflection to test the private method
        $reflection = new \ReflectionClass($this->server);
        $method = $reflection->getMethod('getSwaggerUIHtml');
        $method->setAccessible(true);
        
        $html = $method->invoke($this->server);
        
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('swagger-ui', $html);
        $this->assertStringContainsString('WebSocket', $html);
    }
}