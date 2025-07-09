<?php

namespace LaravelSpectrum\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use LaravelSpectrum\Analyzers\RouteAnalyzer;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\Generators\OpenApiGenerator;

class GenerateDocsCommand extends Command
{
    protected $signature = 'spectrum:generate 
                            {--format=json : Output format (json|yaml)}
                            {--output= : Output file path}
                            {--no-cache : Disable cache}
                            {--clear-cache : Clear cache before generation}';

    protected $description = 'Generate API documentation';

    protected RouteAnalyzer $routeAnalyzer;

    protected OpenApiGenerator $openApiGenerator;

    protected DocumentationCache $cache;

    public function __construct(
        RouteAnalyzer $routeAnalyzer,
        OpenApiGenerator $openApiGenerator,
        DocumentationCache $cache
    ) {
        parent::__construct();

        $this->routeAnalyzer = $routeAnalyzer;
        $this->openApiGenerator = $openApiGenerator;
        $this->cache = $cache;
    }

    public function handle(): int
    {
        if ($this->option('clear-cache')) {
            $this->info('🧹 Clearing cache...');
            $this->cache->clear();
        }

        if ($this->option('no-cache')) {
            config(['spectrum.cache.enabled' => false]);
        }

        $startTime = microtime(true);

        $this->info('🔍 Analyzing routes...');

        // デバッグモードでキャッシュの使用状況を表示
        if ($this->output->isVerbose()) {
            $cacheKeys = $this->cache->getAllCacheKeys();
            $hasRoutesCache = in_array('routes:all', $cacheKeys);
            $this->info('  📊 Using cached routes: '.($hasRoutesCache ? 'Yes' : 'No'));
        }

        $routes = $this->routeAnalyzer->analyze();

        if (empty($routes)) {
            $this->warn('No API routes found. Make sure your routes match the patterns in config/spectrum.php');

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
        $result = File::put($outputPath, $content);

        if ($result === false) {
            $this->error("❌ Failed to write documentation to: {$outputPath}");

            return 1;
        }

        $this->info("✅ Documentation generated: {$outputPath}");

        // デバッグ情報
        if (! $this->option('quiet')) {
            $fileSize = File::size($outputPath);
            $this->info('   📁 File size: '.number_format($fileSize).' bytes');
            $this->info('   📍 Absolute path: '.realpath($outputPath));
        }

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);

        $this->info("⏱️  Generation completed in {$duration} seconds");

        // キャッシュ統計を表示
        if (! $this->option('no-cache')) {
            $stats = $this->cache->getStats();
            $this->info("💾 Cache: {$stats['total_files']} files, {$stats['total_size_human']}");
        }

        return 0;
    }

    protected function getDefaultOutputPath(): string
    {
        $format = $this->option('format');

        // パッケージ開発環境かどうかを判定
        if (function_exists('storage_path')) {
            return storage_path("app/spectrum/openapi.{$format}");
        }

        // パッケージ開発環境の場合は、現在のディレクトリに生成
        $outputDir = getcwd().'/storage/spectrum';
        File::ensureDirectoryExists($outputDir);

        return $outputDir."/openapi.{$format}";
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
            $yaml .= str_repeat('  ', $indent).$key.': ';

            if (is_array($value)) {
                $yaml .= "\n".$this->arrayToYaml($value, $indent + 1);
            } else {
                $yaml .= $value."\n";
            }
        }

        return $yaml;
    }
}
