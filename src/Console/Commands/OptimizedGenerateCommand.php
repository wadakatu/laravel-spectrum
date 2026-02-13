<?php

declare(strict_types=1);

namespace LaravelSpectrum\Console\Commands;

use Illuminate\Console\Command;
use LaravelSpectrum\Analyzers\RouteAnalyzer;
use LaravelSpectrum\Cache\IncrementalCache;
use LaravelSpectrum\DTO\OpenApiServer;
use LaravelSpectrum\Generators\OpenApiGenerator;
use LaravelSpectrum\Performance\ChunkProcessor;
use LaravelSpectrum\Performance\DependencyGraph;
use LaravelSpectrum\Performance\MemoryManager;
use LaravelSpectrum\Performance\ParallelProcessor;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * @phpstan-type RouteArray array<int, array<string, mixed>>
 * @phpstan-type LegacyPathData array{path: string, methods: array<string, mixed>}
 * @phpstan-type OpenApiPaths array<string, array<string, mixed>>
 * @phpstan-type OpenApiComponents array<string, mixed>
 * @phpstan-type OpenApiTag array{name: string, description?: string}
 * @phpstan-type OpenApiTagGroup array{name: string, tags: array<int, string>}
 * @phpstan-type OpenApiDocument array{
 *     openapi?: string,
 *     info?: array<string, mixed>,
 *     servers?: array<int, array<string, mixed>>,
 *     paths?: OpenApiPaths,
 *     components?: OpenApiComponents,
 *     security?: array<int, array<string, array<int, string>>>,
 *     tags?: array<int, OpenApiTag>,
 *     x-tagGroups?: array<int, OpenApiTagGroup>,
 *     webhooks?: array<string, mixed>|\stdClass,
 *     jsonSchemaDialect?: string
 * }
 * @phpstan-type PathsArray array<int, LegacyPathData|OpenApiPaths>
 * @phpstan-type OpenApiSpec array<string, mixed>
 */
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

        $this->configureMemoryLimit();

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

    /** @return RouteArray */
    private function analyzeRoutes(): array
    {
        // RouteAnalyzer を使用してルートを分析
        return $this->routeAnalyzer->analyze();
    }

    /**
     * @param  RouteArray  $routes
     * @return OpenApiSpec
     */
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
                $chunkResults[] = $this->openApiGenerator->generate([$route])->toArray();
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

    /**
     * @param  RouteArray  $routes
     * @return OpenApiSpec
     */
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
                return $this->openApiGenerator->generate([$route])->toArray();
            },
            function ($current, $total) use ($progressBar) {
                $progressBar->setProgress($current);
            }
        );

        $progressBar->finish();
        $this->newLine();

        return $this->assembleOpenApiSpec($results);
    }

    /** @param RouteArray $routes */
    private function buildDependencyGraph(array $routes): void
    {
        $this->info('Building dependency graph...');
        $this->dependencyGraph->buildFromRoutes($routes);
    }

    /**
     * @param  RouteArray  $routes
     * @return RouteArray
     */
    private function filterChangedRoutes(array $routes): array
    {
        $cache = new IncrementalCache($this->dependencyGraph);
        $invalidated = array_values(array_unique($cache->getInvalidatedItems()));

        if ($invalidated === []) {
            if (isset($this->output)) {
                $this->warn('No tracked incremental changes found. Falling back to processing all routes.');
            }

            return $routes;
        }

        return array_values(array_filter($routes, function ($route) use ($invalidated) {
            $routeId = 'route:'.implode(':', $route['httpMethods']).':'.$route['uri'];

            return in_array($routeId, $invalidated, true);
        }));
    }

    /**
     * @param  array<int, OpenApiDocument>  $specs
     * @return OpenApiSpec
     */
    private function assembleOpenApiSpec(array $specs): array
    {
        $base = $this->createBaseOpenApiSpec();

        foreach ($specs as $spec) {
            $base = $this->mergeOpenApiSpecs($base, $spec);
        }

        return $base;
    }

    /**
     * @param  PathsArray  $paths
     * @return array<string, array<string, mixed>>
     */
    private function combinePaths(array $paths): array
    {
        $combined = [];

        foreach ($paths as $pathData) {
            if (! is_array($pathData)) {
                continue;
            }

            // Backward-compatible with the legacy format:
            // ['path' => '/users', 'methods' => ['get' => [...]]]
            if (isset($pathData['path'], $pathData['methods']) && is_string($pathData['path']) && is_array($pathData['methods'])) {
                $combined[$pathData['path']] = array_replace(
                    $combined[$pathData['path']] ?? [],
                    $pathData['methods']
                );

                continue;
            }

            // OpenAPI native format:
            // ['/users' => ['get' => [...]]]
            foreach ($pathData as $path => $methods) {
                if (! is_string($path) || ! is_array($methods)) {
                    continue;
                }

                $combined[$path] = array_replace($combined[$path] ?? [], $methods);
            }
        }

        return $combined;
    }

    /** @return OpenApiDocument */
    private function createBaseOpenApiSpec(): array
    {
        if ($this->openApiGenerator !== null) {
            return $this->openApiGenerator->generate([])->toArray();
        }

        return [
            'openapi' => $this->resolveOpenApiVersion(),
            'info' => [
                'title' => config('spectrum.title', config('app.name').' API'),
                'version' => config('spectrum.version', '1.0.0'),
                'description' => config('spectrum.description', ''),
            ],
            'servers' => OpenApiServer::buildServersFromConfig(),
            'paths' => [],
            'components' => [
                'schemas' => [],
                'securitySchemes' => [],
            ],
        ];
    }

    /**
     * @param  OpenApiDocument  $base
     * @param  OpenApiDocument  $next
     * @return OpenApiDocument
     */
    private function mergeOpenApiSpecs(array $base, array $next): array
    {
        $base['openapi'] = $this->selectOpenApiVersion(
            (string) ($base['openapi'] ?? $this->resolveOpenApiVersion()),
            isset($next['openapi']) ? (string) $next['openapi'] : null
        );

        if (isset($next['info']) && is_array($next['info'])) {
            $base['info'] = array_replace_recursive($base['info'] ?? [], $next['info']);
        }

        if (isset($next['servers']) && is_array($next['servers']) && $next['servers'] !== []) {
            $base['servers'] = $next['servers'];
        }

        $base['paths'] = $this->combinePaths([
            is_array($base['paths'] ?? null) ? $base['paths'] : [],
            is_array($next['paths'] ?? null) ? $next['paths'] : [],
        ]);

        if (isset($next['components']) && is_array($next['components'])) {
            $base['components'] = array_replace_recursive($base['components'] ?? [], $next['components']);
        }

        if (isset($next['security']) && is_array($next['security'])) {
            $base['security'] = $this->mergeSecurityRequirements(
                is_array($base['security'] ?? null) ? $base['security'] : [],
                $next['security']
            );
        }

        if (isset($next['tags']) && is_array($next['tags'])) {
            $base['tags'] = $this->mergeTags(
                is_array($base['tags'] ?? null) ? $base['tags'] : [],
                $next['tags']
            );
        }

        if (isset($next['x-tagGroups']) && is_array($next['x-tagGroups'])) {
            $base['x-tagGroups'] = $this->mergeTagGroups(
                is_array($base['x-tagGroups'] ?? null) ? $base['x-tagGroups'] : [],
                $next['x-tagGroups']
            );
        }

        if (array_key_exists('webhooks', $next)) {
            $base['webhooks'] = $this->mergeWebhookDefinitions($base['webhooks'] ?? null, $next['webhooks']);
        }

        if (array_key_exists('jsonSchemaDialect', $next) && is_string($next['jsonSchemaDialect'])) {
            $base['jsonSchemaDialect'] = $next['jsonSchemaDialect'];
        }

        return $base;
    }

    private function resolveOpenApiVersion(): string
    {
        $version = config('spectrum.openapi.version', '3.0.0');

        return in_array($version, ['3.0.0', '3.1.0'], true) ? $version : '3.0.0';
    }

    private function selectOpenApiVersion(string $baseVersion, ?string $nextVersion): string
    {
        if ($nextVersion === null || ! in_array($nextVersion, ['3.0.0', '3.1.0'], true)) {
            return $baseVersion;
        }

        if (! in_array($baseVersion, ['3.0.0', '3.1.0'], true)) {
            return $nextVersion;
        }

        return version_compare($nextVersion, $baseVersion, '>') ? $nextVersion : $baseVersion;
    }

    /**
     * @param  array<int, array<string, array<int, string>>>  $base
     * @param  array<int, array<string, array<int, string>>>  $next
     * @return array<int, array<string, array<int, string>>>
     */
    private function mergeSecurityRequirements(array $base, array $next): array
    {
        $merged = [];
        $seen = [];

        foreach (array_merge($base, $next) as $requirement) {
            $key = json_encode($requirement);
            if ($key === false || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $merged[] = $requirement;
        }

        return $merged;
    }

    /**
     * @param  array<int, OpenApiTag>  $base
     * @param  array<int, OpenApiTag>  $next
     * @return array<int, OpenApiTag>
     */
    private function mergeTags(array $base, array $next): array
    {
        $merged = [];
        $indexByName = [];

        foreach (array_merge($base, $next) as $tag) {
            if (! isset($tag['name']) || ! is_string($tag['name'])) {
                continue;
            }

            $name = $tag['name'];
            if (! isset($indexByName[$name])) {
                $indexByName[$name] = count($merged);
                $merged[] = $tag;

                continue;
            }

            $existingIndex = $indexByName[$name];
            $merged[$existingIndex] = array_replace($merged[$existingIndex], $tag);
        }

        return $merged;
    }

    /**
     * @param  array<int, OpenApiTagGroup>  $base
     * @param  array<int, OpenApiTagGroup>  $next
     * @return array<int, OpenApiTagGroup>
     */
    private function mergeTagGroups(array $base, array $next): array
    {
        $merged = [];
        $indexByName = [];

        foreach (array_merge($base, $next) as $group) {
            if (! isset($group['name']) || ! is_string($group['name'])) {
                continue;
            }

            $name = $group['name'];
            $tags = $this->normalizeTagList(isset($group['tags']) && is_array($group['tags']) ? $group['tags'] : []);

            if (! isset($indexByName[$name])) {
                $indexByName[$name] = count($merged);
                $merged[] = [
                    'name' => $name,
                    'tags' => $tags,
                ];

                continue;
            }

            $existingIndex = $indexByName[$name];
            $existingTags = $this->normalizeTagList(is_array($merged[$existingIndex]['tags'] ?? null) ? $merged[$existingIndex]['tags'] : []);
            $merged[$existingIndex]['tags'] = $this->normalizeTagList(array_merge($existingTags, $tags));
        }

        return $merged;
    }

    /**
     * @param  array<int, mixed>  $tags
     * @return array<int, string>
     */
    private function normalizeTagList(array $tags): array
    {
        $normalized = [];
        foreach ($tags as $tag) {
            if (is_string($tag) && $tag !== '') {
                $normalized[] = $tag;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param  array<string, mixed>|\stdClass|null  $base
     * @param  array<string, mixed>|\stdClass|null  $next
     * @return array<string, mixed>|\stdClass|null
     */
    private function mergeWebhookDefinitions(array|\stdClass|null $base, array|\stdClass|null $next): array|\stdClass|null
    {
        if ($next === null) {
            return $base;
        }

        if ($base === null) {
            return $next;
        }

        if ($base instanceof \stdClass && $next instanceof \stdClass) {
            return (object) array_replace_recursive((array) $base, (array) $next);
        }

        if (is_array($base) && is_array($next)) {
            return array_replace_recursive($base, $next);
        }

        return $next;
    }

    /** @param OpenApiSpec $openapi */
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

    private function configureMemoryLimit(): void
    {
        $memoryLimit = $this->option('memory-limit');
        if (! is_string($memoryLimit) || trim($memoryLimit) === '') {
            return;
        }

        $parsedLimit = $this->parseMemoryLimit($memoryLimit);
        if ($parsedLimit === null) {
            $this->warn("Invalid memory limit format [{$memoryLimit}]. Skipping limit change.");

            return;
        }

        $currentUsage = memory_get_usage(true);
        if ($parsedLimit !== PHP_INT_MAX && $currentUsage >= $parsedLimit) {
            $this->warn(
                "Requested memory limit [{$memoryLimit}] is below current usage ({$this->formatBytes($currentUsage)}). Skipping limit change."
            );

            return;
        }

        $result = @ini_set('memory_limit', $memoryLimit);
        if ($result === false) {
            $this->warn("Unable to set memory limit to [{$memoryLimit}]. Skipping limit change.");
        }
    }

    private function parseMemoryLimit(string $limit): ?int
    {
        $normalized = trim($limit);
        if ($normalized === '-1') {
            return PHP_INT_MAX;
        }

        if (! preg_match('/^(\d+)\s*([gmk])?$/i', $normalized, $matches)) {
            return null;
        }

        $value = (int) $matches[1];
        $suffix = strtolower($matches[2] ?? '');

        return match ($suffix) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
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
