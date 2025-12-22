<?php

namespace LaravelSpectrum\Generators;

use Illuminate\Support\Facades\View;

class HtmlDocumentGenerator
{
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

        $tryItOutEnabled = $options['try_it_out'] ?? config('spectrum.html.try_it_out', true);

        $data = [
            'title' => $title,
            'version' => $version,
            'description' => $description,
            'spec' => json_encode($openApiSpec, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'tryItOutEnabled' => $tryItOutEnabled,
            'generatedAt' => date('Y-m-d H:i:s'),
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
            // Check if the view exists
            return View::exists('spectrum::swagger-ui');
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
        return view('spectrum::swagger-ui', $data)->render();
    }

    /**
     * Render HTML using simple string replacement (for non-Laravel contexts).
     *
     * @param  array<string, mixed>  $data
     */
    protected function renderWithSimpleTemplate(array $data): string
    {
        $templatePath = __DIR__.'/../../resources/views/swagger-ui.blade.php';

        if (! file_exists($templatePath)) {
            throw new \RuntimeException("Template file not found: {$templatePath}");
        }

        $template = file_get_contents($templatePath);
        if ($template === false) {
            throw new \RuntimeException("Failed to read template file: {$templatePath}");
        }

        // Simple variable replacement for non-Blade context
        $tryItOutJs = $data['tryItOutEnabled'] ? 'true' : 'false';

        $replacements = [
            '{{ $title ?? \'API Documentation\' }}' => htmlspecialchars($data['title'], ENT_QUOTES, 'UTF-8'),
            '{{ $version ?? \'1.0.0\' }}' => htmlspecialchars($data['version'], ENT_QUOTES, 'UTF-8'),
            '{{ $description }}' => htmlspecialchars($data['description'], ENT_QUOTES, 'UTF-8'),
            '{!! $spec !!}' => $data['spec'],
            '{{ $tryItOutEnabled ? \'true\' : \'false\' }}' => $tryItOutJs,
            '{{ $generatedAt ?? date(\'Y-m-d H:i:s\') }}' => $data['generatedAt'],
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
}
