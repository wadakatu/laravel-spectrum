<?php

namespace LaravelSpectrum\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use LaravelSpectrum\Analyzers\RouteAnalyzer;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\DTO\OpenApiSpec;
use LaravelSpectrum\Generators\HtmlDocumentGenerator;
use LaravelSpectrum\Generators\OpenApiGenerator;
use LaravelSpectrum\Support\ErrorCollector;
use Symfony\Component\Yaml\Yaml;

class GenerateDocsCommand extends Command
{
    protected $signature = 'spectrum:generate
                            {--format=json : Output format (json|yaml|html)}
                            {--output= : Output file path}
                            {--no-cache : Disable cache}
                            {--clear-cache : Clear cache before generation}
                            {--fail-on-error : Stop execution on first error}
                            {--ignore-errors : Continue generation ignoring errors}
                            {--error-report= : Save error report to file}
                            {--no-try-it-out : Disable Try It Out feature in HTML output}';

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
        $this->applyConfiguredMemoryLimit();

        $this->info('🚀 Generating API documentation...');

        // エラーコレクターの初期化
        $errorCollector = new ErrorCollector(
            failOnError: (bool) $this->option('fail-on-error')
        );
        $this->laravel->instance(ErrorCollector::class, $errorCollector);

        if ($this->option('clear-cache')) {
            $this->info('🧹 Clearing cache...');
            $this->cache->clear();
        }

        if ($this->option('no-cache')) {
            config(['spectrum.cache.enabled' => false]);
            $this->cache->disable();
        }

        $startTime = microtime(true);

        $this->info('🔍 Analyzing routes...');

        // デバッグモードでキャッシュの使用状況を表示
        if ($this->output->isVerbose()) {
            $cacheKeys = $this->cache->getAllCacheKeys();
            $hasRoutesCache = in_array('routes:all', $cacheKeys);
            $this->info('  📊 Using cached routes: '.($hasRoutesCache ? 'Yes' : 'No'));
        }

        $routes = $this->routeAnalyzer->analyze(! $this->option('no-cache'));

        if (empty($routes)) {
            $this->warn('No API routes found. Make sure your routes match the patterns in config/spectrum.php');

            // エラーレポートの出力（ルートがない場合でも）
            if ($errorCollector->hasErrors() || count($errorCollector->getWarnings()) > 0) {
                $this->outputErrorReport($errorCollector);
            }

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
        if ($this->output->isVerbose()) {
            $fileSize = File::size($outputPath);
            $this->info('   📁 File size: '.number_format($fileSize).' bytes');
            $this->info('   📍 Absolute path: '.realpath($outputPath));
        }

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);

        // エラーレポートの出力
        if ($errorCollector->hasErrors() || count($errorCollector->getWarnings()) > 0) {
            $this->outputErrorReport($errorCollector);
        }

        $this->info("⏱️  Generation completed in {$duration} seconds");

        // キャッシュ統計を表示
        if (! $this->option('no-cache')) {
            $stats = $this->cache->getStats();
            $this->info("💾 Cache: {$stats['total_files']} files, {$stats['total_size_human']}");
        }

        if ($errorCollector->hasErrors() && ! $this->option('ignore-errors')) {
            $this->warn('⚠️  Documentation generated with errors. Use --ignore-errors to suppress this warning.');

            return $this->option('fail-on-error') ? 1 : 0;
        }

        $this->info('✅ Documentation generated successfully!');

        return 0;
    }

    private function applyConfiguredMemoryLimit(): void
    {
        $configuredLimit = config('spectrum.performance.memory_limit');
        if (! is_string($configuredLimit) || trim($configuredLimit) === '') {
            return;
        }

        $currentLimit = ini_get('memory_limit');
        if (! is_string($currentLimit) || trim($currentLimit) === '') {
            return;
        }

        $currentLimitBytes = $this->parseMemoryLimitToBytes($currentLimit);
        $configuredLimitBytes = $this->parseMemoryLimitToBytes($configuredLimit);

        // Unlimited memory or invalid values should not be overridden.
        if ($currentLimitBytes === PHP_INT_MAX || $configuredLimitBytes <= 0) {
            return;
        }

        // Avoid lowering the current memory limit to prevent regressions.
        if ($configuredLimitBytes <= $currentLimitBytes) {
            return;
        }

        ini_set('memory_limit', $configuredLimit);
    }

    private function parseMemoryLimitToBytes(string $limit): int
    {
        $normalizedLimit = trim($limit);

        if ($normalizedLimit === '') {
            return 0;
        }

        if ($normalizedLimit === '-1') {
            return PHP_INT_MAX;
        }

        $unit = strtolower(substr($normalizedLimit, -1));
        $numericValue = $normalizedLimit;

        if (in_array($unit, ['k', 'm', 'g'], true)) {
            $numericValue = substr($normalizedLimit, 0, -1);
        } else {
            $unit = '';
        }

        if (! is_numeric($numericValue)) {
            return 0;
        }

        $bytes = (int) $numericValue;
        if ($bytes < 0) {
            return 0;
        }

        return match ($unit) {
            'g' => $bytes * 1024 * 1024 * 1024,
            'm' => $bytes * 1024 * 1024,
            'k' => $bytes * 1024,
            default => $bytes,
        };
    }

    protected function getDefaultOutputPath(): string
    {
        $format = $this->option('format');
        $extension = $this->getFileExtension($format);

        // パッケージ開発環境かどうかを判定
        if (function_exists('storage_path')) {
            return storage_path("app/spectrum/openapi.{$extension}");
        }

        // パッケージ開発環境の場合は、現在のディレクトリに生成
        $outputDir = getcwd().'/storage/spectrum';
        File::ensureDirectoryExists($outputDir);

        return $outputDir."/openapi.{$extension}";
    }

    protected function getFileExtension(string $format): string
    {
        return match ($format) {
            'html' => 'html',
            'yaml' => 'yaml',
            default => 'json',
        };
    }

    protected function formatOutput(OpenApiSpec|array $openapi, string $format): string
    {
        $data = $openapi instanceof OpenApiSpec ? $openapi->toArray() : $openapi;

        if ($format === 'html') {
            $htmlGenerator = new HtmlDocumentGenerator;

            return $htmlGenerator->generate($data, [
                'try_it_out' => ! $this->option('no-try-it-out'),
            ]);
        }

        if ($format === 'yaml') {
            return $this->arrayToYaml($data);
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    protected function arrayToYaml(array $array): string
    {
        if ($array === []) {
            return '';
        }

        return Yaml::dump($array, 10, 2, Yaml::DUMP_OBJECT_AS_MAP);
    }

    private function outputErrorReport(ErrorCollector $errorCollector): void
    {
        $report = $errorCollector->generateReport();

        if ($this->option('verbose')) {
            // 詳細なエラー情報を表示
            if ($report['summary']['total_errors'] > 0) {
                $this->error("Found {$report['summary']['total_errors']} errors:");
                foreach ($report['errors'] as $error) {
                    $this->error("  - [{$error['context']}] {$error['message']}");
                    if ($this->output->isDebug()) {
                        if (isset($error['metadata']['file'])) {
                            $this->line("    File: {$error['metadata']['file']}");
                        }
                        if (isset($error['metadata']['line'])) {
                            $this->line("    Line: {$error['metadata']['line']}");
                        }
                    }
                }
            }

            if ($report['summary']['total_warnings'] > 0) {
                $this->warn("Found {$report['summary']['total_warnings']} warnings:");
                foreach ($report['warnings'] as $warning) {
                    $this->warn("  - [{$warning['context']}] {$warning['message']}");
                }
            }
        } else {
            // サマリーのみ表示
            if ($report['summary']['total_errors'] > 0) {
                $this->error("Found {$report['summary']['total_errors']} errors during generation.");
            }
            if ($report['summary']['total_warnings'] > 0) {
                $this->warn("Found {$report['summary']['total_warnings']} warnings during generation.");
            }
        }

        // エラーレポートをファイルに保存
        if ($this->option('error-report')) {
            $reportPath = $this->option('error-report');
            File::ensureDirectoryExists(dirname($reportPath));
            file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT));
            $this->info("Error report saved to: {$reportPath}");
        }
    }
}
