<?php

namespace LaravelSpectrum\Console\Commands;

use Illuminate\Console\Command;
use LaravelSpectrum\Analyzers\RouteAnalyzer;
use LaravelSpectrum\Cache\IncrementalCache;
use LaravelSpectrum\Generators\OpenApiGenerator;
use LaravelSpectrum\Performance\ChunkProcessor;
use LaravelSpectrum\Performance\DependencyGraph;
use LaravelSpectrum\Performance\MemoryManager;
use LaravelSpectrum\Performance\ParallelProcessor;
use Symfony\Component\Console\Helper\ProgressBar;

class OptimizedGenerateCommand extends Command
{
    protected $signature = 'spectrum:generate:optimized 
                            {--format=json : Output format (json|yaml)}
                            {--output= : Output file path}
                            {--parallel : Enable parallel processing}
                            {--chunk-size= : Chunk size for processing}
                            {--incremental : Enable incremental generation}
                            {--memory-limit= : Memory limit override}
                            {--workers= : Number of parallel workers}';

    protected $description = 'Generate API documentation with performance optimizations';

    private ChunkProcessor $chunkProcessor;

    private ParallelProcessor $parallelProcessor;

    private DependencyGraph $dependencyGraph;

    private MemoryManager $memoryManager;

    private ?RouteAnalyzer $routeAnalyzer = null;

    private ?OpenApiGenerator $openApiGenerator = null;

    public function __construct(
        ?MemoryManager $memoryManager = null,
        ?ChunkProcessor $chunkProcessor = null,
        ?ParallelProcessor $parallelProcessor = null,
        ?DependencyGraph $dependencyGraph = null,
        ?RouteAnalyzer $routeAnalyzer = null,
        ?OpenApiGenerator $openApiGenerator = null
    ) {
        parent::__construct();

        $this->memoryManager = $memoryManager ?? new MemoryManager;
        $this->chunkProcessor = $chunkProcessor ?? new ChunkProcessor(100, $this->memoryManager);
        $this->parallelProcessor = $parallelProcessor ?? new ParallelProcessor;
        $this->dependencyGraph = $dependencyGraph ?? new DependencyGraph;
        $this->routeAnalyzer = $routeAnalyzer;
        $this->openApiGenerator = $openApiGenerator;
    }

    public function handle(): int
    {
        $this->info('🚀 Generating API documentation with optimizations...');

        // Initialize analyzers and generators if not already injected
        $this->routeAnalyzer = $this->routeAnalyzer ?? app(RouteAnalyzer::class);
        $this->openApiGenerator = $this->openApiGenerator ?? app(OpenApiGenerator::class);

        // メモリ制限の設定
        if ($memoryLimit = $this->option('memory-limit')) {
            ini_set('memory_limit', $memoryLimit);
        }

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        try {
            // ルートの解析
            $routes = $this->analyzeRoutes();

            if (empty($routes)) {
                $this->warn('No API routes found.');

                return 0;
            }

            $this->info(sprintf('Found %d routes to process', count($routes)));

            // 依存関係グラフの構築
            $this->buildDependencyGraph($routes);

            // インクリメンタル生成の場合
            if ($this->option('incremental')) {
                $routes = $this->filterChangedRoutes($routes);
                $this->info(sprintf('Processing %d changed routes', count($routes)));
            }

            // 処理方法の選択
            if ($this->option('parallel') && count($routes) > 50) {
                $openapi = $this->processInParallel($routes);
            } else {
                $openapi = $this->processInChunks($routes);
            }

            // 結果の保存
            $this->saveOutput($openapi);

            // 統計情報の表示
            $this->displayStats($startTime, $startMemory, count($routes));

        } catch (\Exception $e) {
            $this->error('Error: '.$e->getMessage());

            if ($this->option('verbose')) {
                $this->error($e->getTraceAsString());
            }

            return 1;
        }

        return 0;
    }

    private function analyzeRoutes(): array
    {
        // RouteAnalyzer を使用してルートを分析
        return $this->routeAnalyzer->analyze();
    }

    private function processInChunks(array $routes): array
    {
        $this->info('Processing routes in chunks...');

        $chunkSize = $this->option('chunk-size')
            ?? $this->chunkProcessor->calculateOptimalChunkSize(count($routes));

        $progressBar = new ProgressBar($this->output, count($routes));
        $progressBar->start();

        $results = [];
        $generator = $this->chunkProcessor->processInChunks($routes, function ($chunk) use ($progressBar) {
            $chunkResults = [];

            foreach ($chunk as $route) {
                $chunkResults[] = $this->openApiGenerator->generate([$route])['paths'];
                $progressBar->advance();
            }

            return $chunkResults;
        });

        foreach ($generator as $data) {
            $results = array_merge($results, $data['result']);

            // メモリ統計を表示
            if ($this->option('verbose')) {
                $stats = $this->memoryManager->getMemoryStats();
                $this->info(sprintf(
                    "\nMemory: %s / %s (%.2f%%)",
                    $stats['current'],
                    $stats['limit'],
                    $stats['percentage']
                ));
            }
        }

        $progressBar->finish();
        $this->newLine();

        return $this->assembleOpenApiSpec($results);
    }

    private function processInParallel(array $routes): array
    {
        $this->info('Processing routes in parallel...');

        $workers = $this->option('workers') ?? null;
        if ($workers) {
            $this->parallelProcessor->setWorkers((int) $workers);
        }

        $progressBar = new ProgressBar($this->output, count($routes));
        $progressBar->start();

        $results = $this->parallelProcessor->processWithProgress(
            $routes,
            function ($route) {
                return $this->openApiGenerator->generate([$route])['paths'];
            },
            function ($current, $total) use ($progressBar) {
                $progressBar->setProgress($current);
            }
        );

        $progressBar->finish();
        $this->newLine();

        return $this->assembleOpenApiSpec($results);
    }

    private function buildDependencyGraph(array $routes): void
    {
        $this->info('Building dependency graph...');
        $this->dependencyGraph->buildFromRoutes($routes);
    }

    private function filterChangedRoutes(array $routes): array
    {
        $cache = new IncrementalCache($this->dependencyGraph);
        $invalidated = $cache->getInvalidatedItems();

        return array_filter($routes, function ($route) use ($invalidated) {
            $routeId = 'route:'.implode(':', $route['httpMethods']).':'.$route['uri'];

            return in_array($routeId, $invalidated);
        });
    }

    private function assembleOpenApiSpec(array $paths): array
    {
        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => config('app.name', 'Laravel API'),
                'version' => '1.0.0',
                'description' => 'API documentation generated by Laravel Spectrum',
            ],
            'servers' => [
                [
                    'url' => config('app.url', 'http://localhost'),
                    'description' => 'API Server',
                ],
            ],
            'paths' => $this->combinePaths($paths),
            'components' => [
                'schemas' => [],
                'securitySchemes' => [],
            ],
        ];
    }

    private function combinePaths(array $paths): array
    {
        $combined = [];

        foreach ($paths as $pathData) {
            if (isset($pathData['path']) && isset($pathData['methods'])) {
                $path = $pathData['path'];
                if (! isset($combined[$path])) {
                    $combined[$path] = [];
                }
                $combined[$path] = array_merge($combined[$path], $pathData['methods']);
            }
        }

        return $combined;
    }

    private function saveOutput(array $openapi): void
    {
        $format = $this->option('format');
        $outputPath = $this->option('output') ?? storage_path('app/spectrum/openapi.'.$format);

        // ディレクトリが存在しない場合は作成
        $directory = dirname($outputPath);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if ($format === 'yaml') {
            // YAML 形式で保存
            $yaml = \Symfony\Component\Yaml\Yaml::dump($openapi, 10, 2);
            file_put_contents($outputPath, $yaml);
        } else {
            // JSON 形式で保存
            file_put_contents($outputPath, json_encode($openapi, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        $this->info("Documentation saved to: {$outputPath}");
    }

    private function displayStats(float $startTime, int $startMemory, int $routeCount): void
    {
        $duration = microtime(true) - $startTime;
        $peakMemory = memory_get_peak_usage(true) - $startMemory;

        $this->info('');
        $this->info('📊 Generation Statistics:');
        $this->info(sprintf('  ⏱️  Time: %.2f seconds', $duration));
        $this->info(sprintf('  💾 Peak Memory: %s', $this->formatBytes($peakMemory)));
        $this->info(sprintf('  🚀 Performance: %.2f routes/second', $routeCount / $duration));

        $stats = $this->memoryManager->getMemoryStats();
        $this->info(sprintf('  📈 Final Memory: %s / %s (%.2f%%)',
            $stats['current'],
            $stats['limit'],
            $stats['percentage']
        ));
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
