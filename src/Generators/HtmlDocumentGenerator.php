<?php

declare(strict_types=1);

namespace LaravelSpectrum\Generators;

use Illuminate\Support\Facades\View;

class HtmlDocumentGenerator
{
    protected string $viewerTemplate = 'swagger-ui';

    /**
     * Generate HTML documentation from OpenAPI spec.
     *
     * @param  array<string, mixed>  $openApiSpec  The OpenAPI specification array
     * @param  array<string, mixed>  $options  Additional options for HTML generation
     * @return string The generated HTML content
     */
    public function generate(array $openApiSpec, array $options = []): string
    {
        $title = $openApiSpec['info']['title'] ?? config('spectrum.title', 'API Documentation');
        $version = $openApiSpec['info']['version'] ?? config('spectrum.version', '1.0.0');
        $description = $openApiSpec['info']['description'] ?? '';

        $this->viewerTemplate = $this->resolveViewerTemplate($options['viewer'] ?? config('spectrum.html.viewer', 'swagger-ui'));

        $tryItOutEnabled = $options['try_it_out'] ?? config('spectrum.html.try_it_out', true);

        $data = [
            'title' => $title,
            'version' => $version,
            'description' => $description,
            'spec' => json_encode($openApiSpec, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'tryItOutEnabled' => (bool) $tryItOutEnabled,
            'generatedAt' => date('Y-m-d H:i:s'),
            'elementsLayout' => (string) config('spectrum.html.elements.layout', 'sidebar'),
            'elementsRouter' => (string) config('spectrum.html.elements.router', 'hash'),
            'elementsHideTryIt' => (bool) config('spectrum.html.elements.hide_try_it', false),
            'scalarTheme' => (string) config('spectrum.html.scalar.theme', 'default'),
            'scalarDarkMode' => (bool) config('spectrum.html.scalar.dark_mode', true),
            'scalarShowSidebar' => (bool) config('spectrum.html.scalar.show_sidebar', true),
            'rapidocTheme' => (string) config('spectrum.html.rapidoc.theme', 'light'),
            'rapidocRenderStyle' => (string) config('spectrum.html.rapidoc.render_style', 'read'),
            'rapidocSchemaStyle' => (string) config('spectrum.html.rapidoc.schema_style', 'tree'),
        ];

        // Check if we're in a Laravel application context with views
        if ($this->canUseBladeViews()) {
            return $this->renderWithBlade($data);
        }

        // Fallback to simple template rendering
        return $this->renderWithSimpleTemplate($data);
    }

    /**
     * Check if Blade views can be used.
     */
    protected function canUseBladeViews(): bool
    {
        if (! function_exists('view')) {
            return false;
        }

        try {
            // Check if the selected view exists
            return View::exists('spectrum::'.$this->viewerTemplate);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Render HTML using Blade template.
     *
     * @param  array<string, mixed>  $data
     */
    protected function renderWithBlade(array $data): string
    {
        return view('spectrum::'.$this->viewerTemplate, $data)->render();
    }

    /**
     * Render HTML using simple string replacement (for non-Laravel contexts).
     *
     * @param  array<string, mixed>  $data
     */
    protected function renderWithSimpleTemplate(array $data): string
    {
        $templatePath = __DIR__.'/../../resources/views/'.$this->viewerTemplate.'.blade.php';

        if (! file_exists($templatePath)) {
            throw new \RuntimeException("Template file not found: {$templatePath}");
        }

        $template = file_get_contents($templatePath);
        if ($template === false) {
            throw new \RuntimeException("Failed to read template file: {$templatePath}");
        }

        // Simple variable replacement for non-Blade context
        $replacements = [
            '{{ $title ?? \'API Documentation\' }}' => htmlspecialchars((string) $data['title'], ENT_QUOTES, 'UTF-8'),
            '{{ $version ?? \'1.0.0\' }}' => htmlspecialchars((string) $data['version'], ENT_QUOTES, 'UTF-8'),
            '{{ $description }}' => htmlspecialchars((string) $data['description'], ENT_QUOTES, 'UTF-8'),
            '{!! $spec !!}' => (string) $data['spec'],
            '{{ $tryItOutEnabled ? \'true\' : \'false\' }}' => $this->toJsBool($data['tryItOutEnabled'] ?? true),
            '{{ $generatedAt ?? date(\'Y-m-d H:i:s\') }}' => (string) $data['generatedAt'],
            '{{ $elementsLayout ?? \'sidebar\' }}' => htmlspecialchars((string) ($data['elementsLayout'] ?? 'sidebar'), ENT_QUOTES, 'UTF-8'),
            '{{ $elementsRouter ?? \'hash\' }}' => htmlspecialchars((string) ($data['elementsRouter'] ?? 'hash'), ENT_QUOTES, 'UTF-8'),
            '{{ $elementsHideTryIt ? \'true\' : \'false\' }}' => $this->toJsBool($data['elementsHideTryIt'] ?? false),
            '{{ $scalarTheme ?? \'default\' }}' => htmlspecialchars((string) ($data['scalarTheme'] ?? 'default'), ENT_QUOTES, 'UTF-8'),
            '{{ $scalarDarkMode ? \'true\' : \'false\' }}' => $this->toJsBool($data['scalarDarkMode'] ?? true),
            '{{ $scalarShowSidebar ? \'true\' : \'false\' }}' => $this->toJsBool($data['scalarShowSidebar'] ?? true),
            '{{ $rapidocTheme ?? \'light\' }}' => htmlspecialchars((string) ($data['rapidocTheme'] ?? 'light'), ENT_QUOTES, 'UTF-8'),
            '{{ $rapidocRenderStyle ?? \'read\' }}' => htmlspecialchars((string) ($data['rapidocRenderStyle'] ?? 'read'), ENT_QUOTES, 'UTF-8'),
            '{{ $rapidocSchemaStyle ?? \'tree\' }}' => htmlspecialchars((string) ($data['rapidocSchemaStyle'] ?? 'tree'), ENT_QUOTES, 'UTF-8'),
        ];

        $html = str_replace(array_keys($replacements), array_values($replacements), $template);

        // Handle conditional blocks
        if (empty($data['description'])) {
            $html = preg_replace('/@if\(!empty\(\$description\)\).*?@endif/s', '', $html);
        } else {
            $html = preg_replace('/@if\(!empty\(\$description\)\)\s*/', '', $html);
            $html = preg_replace('/@endif/', '', $html);
        }

        return $html;
    }

    protected function resolveViewerTemplate(mixed $viewer): string
    {
        if (! is_string($viewer)) {
            return 'swagger-ui';
        }

        $normalized = strtolower(trim($viewer));
        $normalized = str_replace(['_', ' '], '-', $normalized);

        return match ($normalized) {
            'swagger-ui', 'swagger', 'swaggerui' => 'swagger-ui',
            'elements', 'stoplight-elements', 'stoplight' => 'elements',
            'scalar' => 'scalar',
            'rapidoc', 'rapi-doc', 'rapi' => 'rapidoc',
            default => 'swagger-ui',
        };
    }

    protected function toJsBool(mixed $value): string
    {
        return $value ? 'true' : 'false';
    }
}
