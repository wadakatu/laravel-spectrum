<?php

namespace LaravelSpectrum\Tests\Unit\Generators;

use LaravelSpectrum\Generators\HtmlDocumentGenerator;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class HtmlDocumentGeneratorTest extends TestCase
{
    private HtmlDocumentGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new HtmlDocumentGenerator;
    }

    #[Test]
    public function it_generates_html_with_swagger_ui(): void
    {
        $spec = $this->getSampleOpenApiSpec();

        $html = $this->generator->generate($spec);

        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('swagger-ui', $html);
        $this->assertStringContainsString('SwaggerUIBundle', $html);
    }

    #[Test]
    public function it_includes_api_title_in_html(): void
    {
        $spec = $this->getSampleOpenApiSpec();

        $html = $this->generator->generate($spec);

        $this->assertStringContainsString('Test API', $html);
    }

    #[Test]
    public function it_includes_api_version_in_html(): void
    {
        $spec = $this->getSampleOpenApiSpec();

        $html = $this->generator->generate($spec);

        $this->assertStringContainsString('v1.0.0', $html);
    }

    #[Test]
    public function it_includes_api_description_when_provided(): void
    {
        $spec = $this->getSampleOpenApiSpec();

        $html = $this->generator->generate($spec);

        $this->assertStringContainsString('Test API description', $html);
    }

    #[Test]
    public function it_embeds_openapi_spec_as_json(): void
    {
        $spec = $this->getSampleOpenApiSpec();

        $html = $this->generator->generate($spec);

        // The spec should be embedded as JSON
        $this->assertStringContainsString('"openapi":"3.0.0"', $html);
        $this->assertStringContainsString('"/api/users"', $html);
    }

    #[Test]
    public function it_enables_try_it_out_by_default(): void
    {
        $spec = $this->getSampleOpenApiSpec();

        $html = $this->generator->generate($spec);

        $this->assertStringContainsString('tryItOutEnabled: true', $html);
    }

    #[Test]
    public function it_can_disable_try_it_out(): void
    {
        $spec = $this->getSampleOpenApiSpec();

        $html = $this->generator->generate($spec, ['try_it_out' => false]);

        $this->assertStringContainsString('tryItOutEnabled: false', $html);
    }

    #[Test]
    public function it_includes_spectrum_branding(): void
    {
        $spec = $this->getSampleOpenApiSpec();

        $html = $this->generator->generate($spec);

        $this->assertStringContainsString('Laravel Spectrum', $html);
        $this->assertStringContainsString('github.com/wadakatu/laravel-spectrum', $html);
    }

    #[Test]
    public function it_includes_generation_timestamp(): void
    {
        $spec = $this->getSampleOpenApiSpec();

        $html = $this->generator->generate($spec);

        // Should contain a date in format YYYY-MM-DD
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $html);
    }

    #[Test]
    public function it_loads_swagger_ui_from_cdn(): void
    {
        $spec = $this->getSampleOpenApiSpec();

        $html = $this->generator->generate($spec);

        $this->assertStringContainsString('unpkg.com/swagger-ui-dist', $html);
        $this->assertStringContainsString('swagger-ui-bundle.js', $html);
        $this->assertStringContainsString('swagger-ui.css', $html);
    }

    #[Test]
    public function it_handles_spec_without_description(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Test API',
                'version' => '1.0.0',
            ],
            'paths' => [],
        ];

        $html = $this->generator->generate($spec);

        $this->assertStringContainsString('Test API', $html);
        // Should not contain empty description paragraph
        $this->assertStringNotContainsString('<p></p>', $html);
    }

    #[Test]
    public function it_escapes_html_entities_in_title(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Test <script>alert("xss")</script> API',
                'version' => '1.0.0',
            ],
            'paths' => [],
        ];

        $html = $this->generator->generate($spec);

        // Should escape HTML entities
        $this->assertStringNotContainsString('<script>alert("xss")</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    #[Test]
    public function it_includes_deep_linking_option(): void
    {
        $spec = $this->getSampleOpenApiSpec();

        $html = $this->generator->generate($spec);

        $this->assertStringContainsString('deepLinking: true', $html);
    }

    #[Test]
    public function it_includes_filter_option(): void
    {
        $spec = $this->getSampleOpenApiSpec();

        $html = $this->generator->generate($spec);

        $this->assertStringContainsString('filter: true', $html);
    }

    /**
     * @return array<string, mixed>
     */
    private function getSampleOpenApiSpec(): array
    {
        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Test API',
                'version' => '1.0.0',
                'description' => 'Test API description',
            ],
            'paths' => [
                '/api/users' => [
                    'get' => [
                        'summary' => 'List users',
                        'responses' => [
                            '200' => [
                                'description' => 'Success',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
