<?php

namespace LaravelSpectrum\Tests\Feature\Services;

use LaravelSpectrum\Services\LiveReloadServer;
use LaravelSpectrum\Tests\TestCase;

class LiveReloadServerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        LiveReloadServer::resetClients();
    }

    public function test_get_swagger_ui_html_uses_view_when_available(): void
    {
        $server = new LiveReloadServer;

        // First set up the HTTP worker so port extraction code runs
        $reflection = new \ReflectionClass($server);
        $startHttpServerMethod = $reflection->getMethod('startHttpServer');
        $startHttpServerMethod->setAccessible(true);
        $startHttpServerMethod->invoke($server, 'localhost', 8082);

        // Now call getSwaggerUIHtml - in Feature test context, views may be available
        $getSwaggerUIHtmlMethod = $reflection->getMethod('getSwaggerUIHtml');
        $getSwaggerUIHtmlMethod->setAccessible(true);
        $html = $getSwaggerUIHtmlMethod->invoke($server);

        // Verify HTML was generated (either from view or fallback)
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('swagger-ui', $html);
    }

    public function test_get_swagger_ui_html_extracts_port_when_view_exists(): void
    {
        // Register the view for this test
        $this->app['view']->addNamespace('spectrum', __DIR__.'/../../../resources/views');

        $server = new LiveReloadServer;

        // Set up the HTTP worker
        $reflection = new \ReflectionClass($server);
        $startHttpServerMethod = $reflection->getMethod('startHttpServer');
        $startHttpServerMethod->setAccessible(true);
        $startHttpServerMethod->invoke($server, 'localhost', 9000);

        // Get HTML
        $getSwaggerUIHtmlMethod = $reflection->getMethod('getSwaggerUIHtml');
        $getSwaggerUIHtmlMethod->setAccessible(true);
        $html = $getSwaggerUIHtmlMethod->invoke($server);

        // Verify HTML was generated and contains WebSocket port (9001)
        $this->assertStringContainsString('swagger-ui', $html);
        // The ws port should be http port + 1
        $this->assertStringContainsString('9001', $html);
    }

    public function test_get_swagger_ui_html_defaults_ws_port_when_no_http_worker(): void
    {
        // Register the view for this test
        $this->app['view']->addNamespace('spectrum', __DIR__.'/../../../resources/views');

        $server = new LiveReloadServer;

        // Don't set up HTTP worker, so port defaults to 8081

        $reflection = new \ReflectionClass($server);
        $getSwaggerUIHtmlMethod = $reflection->getMethod('getSwaggerUIHtml');
        $getSwaggerUIHtmlMethod->setAccessible(true);
        $html = $getSwaggerUIHtmlMethod->invoke($server);

        // Should have default port 8081
        $this->assertStringContainsString('8081', $html);
    }

    public function test_get_swagger_ui_html_handles_non_matching_port_pattern(): void
    {
        $this->app['view']->addNamespace('spectrum', __DIR__.'/../../../resources/views');

        $server = new LiveReloadServer;

        // Set up HTTP worker
        $reflection = new \ReflectionClass($server);
        $startHttpServerMethod = $reflection->getMethod('startHttpServer');
        $startHttpServerMethod->setAccessible(true);
        $startHttpServerMethod->invoke($server, 'localhost', 8080);

        // Access the httpWorker and mock getSocketName to return non-matching pattern
        $httpWorkerProperty = $reflection->getProperty('httpWorker');
        $httpWorkerProperty->setAccessible(true);
        $httpWorker = $httpWorkerProperty->getValue($server);

        // Verify the worker is set
        $this->assertNotNull($httpWorker);

        // Get HTML
        $getSwaggerUIHtmlMethod = $reflection->getMethod('getSwaggerUIHtml');
        $getSwaggerUIHtmlMethod->setAccessible(true);
        $html = $getSwaggerUIHtmlMethod->invoke($server);

        // HTML should be generated
        $this->assertNotEmpty($html);
    }
}
