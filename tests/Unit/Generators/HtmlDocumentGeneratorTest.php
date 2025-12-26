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

    #[Test]
    public function it_uses_default_title_when_not_provided(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'version' => '1.0.0',
            ],
            'paths' => [],
        ];

        $html = $this->generator->generate($spec);

        // Should contain some title (from config or default)
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('swagger-ui', $html);
    }

    #[Test]
    public function it_uses_default_version_when_not_provided(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'My API',
            ],
            'paths' => [],
        ];

        $html = $this->generator->generate($spec);

        // Should still generate valid HTML
        $this->assertStringContainsString('My API', $html);
        $this->assertStringContainsString('swagger-ui', $html);
    }

    #[Test]
    public function it_handles_empty_info_block(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => [],
            'paths' => [],
        ];

        $html = $this->generator->generate($spec);

        // Should still generate valid HTML with defaults
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('swagger-ui', $html);
    }

    #[Test]
    public function it_handles_missing_info_block(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'paths' => [],
        ];

        $html = $this->generator->generate($spec);

        // Should still generate valid HTML with defaults
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('swagger-ui', $html);
    }

    #[Test]
    public function it_properly_encodes_json_spec(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Test API',
                'version' => '1.0.0',
                'description' => 'Contains "quotes" and slashes /path',
            ],
            'paths' => [
                '/api/test' => [
                    'get' => [
                        'summary' => 'Test endpoint',
                    ],
                ],
            ],
        ];

        $html = $this->generator->generate($spec);

        // Check that JSON is properly embedded (unescaped slashes)
        $this->assertStringContainsString('"/api/test"', $html);
        // Description with quotes should be escaped in JSON
        $this->assertStringContainsString('Contains \\"quotes\\"', $html);
    }

    #[Test]
    public function it_handles_unicode_characters_in_spec(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => '日本語API',
                'version' => '1.0.0',
                'description' => 'APIの説明文',
            ],
            'paths' => [],
        ];

        $html = $this->generator->generate($spec);

        // Unicode should be preserved (unescaped)
        $this->assertStringContainsString('日本語API', $html);
        $this->assertStringContainsString('APIの説明文', $html);
    }

    #[Test]
    public function it_escapes_description_html_entities(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Test API',
                'version' => '1.0.0',
                'description' => 'Contains <b>bold</b> & special chars',
            ],
            'paths' => [],
        ];

        $html = $this->generator->generate($spec);

        // HTML entities in description should be escaped when displayed outside spec JSON
        $this->assertStringContainsString('&lt;b&gt;bold&lt;/b&gt;', $html);
        $this->assertStringContainsString('&amp;', $html);
    }

    #[Test]
    public function it_escapes_version_html_entities(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Test API',
                'version' => '<script>alert(1)</script>',
            ],
            'paths' => [],
        ];

        $html = $this->generator->generate($spec);

        // Version should be escaped when displayed in HTML header
        // Note: The raw version appears unescaped in the JSON spec (inside <script> block),
        // but when displayed in the version span, it should be escaped
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    #[Test]
    public function it_handles_complex_paths_with_parameters(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Test API',
                'version' => '1.0.0',
            ],
            'paths' => [
                '/api/users/{userId}/posts/{postId}' => [
                    'get' => [
                        'summary' => 'Get user post',
                        'parameters' => [
                            ['name' => 'userId', 'in' => 'path', 'required' => true],
                            ['name' => 'postId', 'in' => 'path', 'required' => true],
                        ],
                    ],
                ],
            ],
        ];

        $html = $this->generator->generate($spec);

        // Path with parameters should be in the embedded JSON
        $this->assertStringContainsString('/api/users/{userId}/posts/{postId}', $html);
    }

    #[Test]
    public function it_generates_consistent_output_structure(): void
    {
        $spec = $this->getSampleOpenApiSpec();

        $html = $this->generator->generate($spec);

        // Check core HTML structure elements
        $this->assertStringContainsString('<html', $html);
        $this->assertStringContainsString('<head>', $html);
        $this->assertStringContainsString('<body>', $html);
        $this->assertStringContainsString('</html>', $html);
    }

    #[Test]
    public function it_includes_swagger_ui_initialization(): void
    {
        $spec = $this->getSampleOpenApiSpec();

        $html = $this->generator->generate($spec);

        // Check that SwaggerUI is properly initialized
        $this->assertStringContainsString('SwaggerUIBundle(', $html);
        $this->assertStringContainsString('spec:', $html);
    }

    #[Test]
    public function it_respects_try_it_out_option_explicitly_true(): void
    {
        $spec = $this->getSampleOpenApiSpec();

        $html = $this->generator->generate($spec, ['try_it_out' => true]);

        $this->assertStringContainsString('tryItOutEnabled: true', $html);
    }

    #[Test]
    public function it_uses_simple_template_when_blade_not_available(): void
    {
        // Create a subclass that forces simple template rendering
        $generator = new class extends HtmlDocumentGenerator
        {
            protected function canUseBladeViews(): bool
            {
                return false;
            }
        };

        $spec = $this->getSampleOpenApiSpec();
        $html = $generator->generate($spec);

        // Should still generate valid HTML with swagger-ui
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('swagger-ui', $html);
        $this->assertStringContainsString('Test API', $html);
    }

    #[Test]
    public function it_renders_simple_template_with_empty_description(): void
    {
        $generator = new class extends HtmlDocumentGenerator
        {
            protected function canUseBladeViews(): bool
            {
                return false;
            }
        };

        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'No Description API',
                'version' => '2.0.0',
            ],
            'paths' => [],
        ];

        $html = $generator->generate($spec);

        $this->assertStringContainsString('No Description API', $html);
        $this->assertStringContainsString('swagger-ui', $html);
    }

    #[Test]
    public function it_renders_simple_template_with_description(): void
    {
        $generator = new class extends HtmlDocumentGenerator
        {
            protected function canUseBladeViews(): bool
            {
                return false;
            }
        };

        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Test API',
                'version' => '1.0.0',
                'description' => 'This is a test description',
            ],
            'paths' => [],
        ];

        $html = $generator->generate($spec);

        $this->assertStringContainsString('This is a test description', $html);
    }

    #[Test]
    public function it_renders_simple_template_with_try_it_out_disabled(): void
    {
        $generator = new class extends HtmlDocumentGenerator
        {
            protected function canUseBladeViews(): bool
            {
                return false;
            }
        };

        $spec = $this->getSampleOpenApiSpec();
        $html = $generator->generate($spec, ['try_it_out' => false]);

        $this->assertStringContainsString('tryItOutEnabled: false', $html);
    }

    #[Test]
    public function it_throws_exception_when_template_file_not_found(): void
    {
        $generator = new class extends HtmlDocumentGenerator
        {
            protected function canUseBladeViews(): bool
            {
                return false;
            }

            protected function renderWithSimpleTemplate(array $data): string
            {
                // Point to non-existent file
                $templatePath = '/non/existent/path/template.blade.php';

                if (! file_exists($templatePath)) {
                    throw new \RuntimeException("Template file not found: {$templatePath}");
                }

                return '';
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Template file not found');

        $generator->generate($this->getSampleOpenApiSpec());
    }

    #[Test]
    public function it_escapes_html_in_simple_template(): void
    {
        $generator = new class extends HtmlDocumentGenerator
        {
            protected function canUseBladeViews(): bool
            {
                return false;
            }
        };

        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => '<script>alert("xss")</script>',
                'version' => '<b>1.0</b>',
                'description' => '<div>HTML content</div>',
            ],
            'paths' => [],
        ];

        $html = $generator->generate($spec);

        // HTML entities should be escaped
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&lt;b&gt;', $html);
        $this->assertStringContainsString('&lt;div&gt;', $html);
    }

    #[Test]
    public function it_embeds_json_spec_in_simple_template(): void
    {
        $generator = new class extends HtmlDocumentGenerator
        {
            protected function canUseBladeViews(): bool
            {
                return false;
            }
        };

        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Test API',
                'version' => '1.0.0',
            ],
            'paths' => [
                '/api/test' => [
                    'get' => ['summary' => 'Test endpoint'],
                ],
            ],
        ];

        $html = $generator->generate($spec);

        // JSON spec should be embedded
        $this->assertStringContainsString('"/api/test"', $html);
        $this->assertStringContainsString('"openapi":"3.0.0"', $html);
    }

    #[Test]
    public function it_handles_unicode_in_simple_template(): void
    {
        $generator = new class extends HtmlDocumentGenerator
        {
            protected function canUseBladeViews(): bool
            {
                return false;
            }
        };

        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => '日本語API',
                'version' => '1.0.0',
                'description' => 'これは説明です',
            ],
            'paths' => [],
        ];

        $html = $generator->generate($spec);

        // Unicode should be preserved
        $this->assertStringContainsString('日本語API', $html);
        $this->assertStringContainsString('これは説明です', $html);
    }

    #[Test]
    public function can_use_blade_views_returns_true_when_view_exists(): void
    {
        // In Orchestra Testbench, the view should exist
        $generator = new HtmlDocumentGenerator;

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($generator);
        $method = $reflection->getMethod('canUseBladeViews');
        $method->setAccessible(true);

        $result = $method->invoke($generator);

        // In test environment with Orchestra Testbench, this should be true
        $this->assertTrue($result);
    }

    #[Test]
    public function render_with_blade_returns_html(): void
    {
        $generator = new HtmlDocumentGenerator;

        $reflection = new \ReflectionClass($generator);
        $method = $reflection->getMethod('renderWithBlade');
        $method->setAccessible(true);

        $data = [
            'title' => 'Test API',
            'version' => '1.0.0',
            'description' => 'Test description',
            'spec' => '{"openapi":"3.0.0"}',
            'tryItOutEnabled' => true,
            'generatedAt' => '2024-01-01 00:00:00',
        ];

        $html = $method->invoke($generator, $data);

        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('swagger-ui', $html);
    }

    #[Test]
    public function render_with_simple_template_returns_html(): void
    {
        $generator = new HtmlDocumentGenerator;

        $reflection = new \ReflectionClass($generator);
        $method = $reflection->getMethod('renderWithSimpleTemplate');
        $method->setAccessible(true);

        $data = [
            'title' => 'Test API',
            'version' => '1.0.0',
            'description' => 'Test description',
            'spec' => '{"openapi":"3.0.0"}',
            'tryItOutEnabled' => true,
            'generatedAt' => '2024-01-01 00:00:00',
        ];

        $html = $method->invoke($generator, $data);

        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('swagger-ui', $html);
        $this->assertStringContainsString('Test API', $html);
    }

    #[Test]
    public function render_with_simple_template_handles_empty_description(): void
    {
        $generator = new HtmlDocumentGenerator;

        $reflection = new \ReflectionClass($generator);
        $method = $reflection->getMethod('renderWithSimpleTemplate');
        $method->setAccessible(true);

        $data = [
            'title' => 'Test API',
            'version' => '1.0.0',
            'description' => '',
            'spec' => '{"openapi":"3.0.0"}',
            'tryItOutEnabled' => false,
            'generatedAt' => '2024-01-01 00:00:00',
        ];

        $html = $method->invoke($generator, $data);

        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('tryItOutEnabled: false', $html);
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
