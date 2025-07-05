<?php

namespace LaravelPrism\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use LaravelPrism\Analyzers\RouteAnalyzer;
use LaravelPrism\Generators\OpenApiGenerator;

class GenerateDocsCommand extends Command
{
    protected $signature = 'prism:generate 
                            {--format=json : Output format (json|yaml)}
                            {--output= : Output file path}';

    protected $description = 'Generate API documentation';

    protected RouteAnalyzer $routeAnalyzer;
    protected OpenApiGenerator $openApiGenerator;

    public function __construct(
        RouteAnalyzer $routeAnalyzer,
        OpenApiGenerator $openApiGenerator
    ) {
        parent::__construct();

        $this->routeAnalyzer    = $routeAnalyzer;
        $this->openApiGenerator = $openApiGenerator;
    }

    public function handle(): int
    {
        $this->info('🔍 Analyzing routes...');

        $routes = $this->routeAnalyzer->analyze();

        if (empty($routes)) {
            $this->warn('No API routes found. Make sure your routes match the patterns in config/prism.php');

            return 1;
        }

        $this->info(sprintf('Found %d API routes', count($routes)));

        $this->info('📝 Generating OpenAPI specification...');

        $openapi = $this->openApiGenerator->generate($routes);

        // 出力パスの決定
        $outputPath = $this->option('output') ?: $this->getDefaultOutputPath();

        // ディレクトリの作成
        File::ensureDirectoryExists(dirname($outputPath));

        // ファイルの保存
        $content = $this->formatOutput($openapi, $this->option('format'));
        File::put($outputPath, $content);

        $this->info("✅ Documentation generated: {$outputPath}");

        return 0;
    }

    protected function getDefaultOutputPath(): string
    {
        $format = $this->option('format');

        return storage_path("app/prism/openapi.{$format}");
    }

    protected function formatOutput(array $openapi, string $format): string
    {
        if ($format === 'yaml') {
            // 簡易的なYAML変換（本番ではsymfony/yamlを使用）
            return $this->arrayToYaml($openapi);
        }

        return json_encode($openapi, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    protected function arrayToYaml(array $array, int $indent = 0): string
    {
        // MVP版の簡易実装
        $yaml = '';
        foreach ($array as $key => $value) {
            $yaml .= str_repeat('  ', $indent) . $key . ': ';

            if (is_array($value)) {
                $yaml .= "\n" . $this->arrayToYaml($value, $indent + 1);
            } else {
                $yaml .= $value . "\n";
            }
        }

        return $yaml;
    }
}
