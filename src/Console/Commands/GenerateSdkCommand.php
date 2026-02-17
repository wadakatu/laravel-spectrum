<?php

declare(strict_types=1);

namespace LaravelSpectrum\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use LaravelSpectrum\SDK\SdkCommandBuilder;
use LaravelSpectrum\SDK\SdkCommandRunnerInterface;
use Throwable;

class GenerateSdkCommand extends Command
{
    protected $signature = 'spectrum:sdk
                            {language : Target language (typescript|swift|kotlin)}
                            {--spec= : Path to existing OpenAPI specification file}
                            {--output= : Output directory}
                            {--generator= : SDK generator (openapi-generator|openapi-typescript|kiota)}
                            {--package-name= : Package/namespace name override}
                            {--dry-run : Show generator command without executing}';

    protected $description = 'Generate type-safe SDK client code from OpenAPI documentation';

    private const DEFAULT_GENERATOR = 'openapi-generator';

    private const SUPPORTED_GENERATORS = [
        'openapi-generator',
        'openapi-typescript',
        'kiota',
    ];

    private ?string $temporarySpecPath = null;

    public function __construct(
        private readonly SdkCommandBuilder $commandBuilder,
        private readonly SdkCommandRunnerInterface $commandRunner
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('🚀 Generating SDK client...');

        try {
            $language = $this->resolveLanguage();
            $languageConfig = $this->resolveLanguageConfig($language);
            $generator = $this->resolveGenerator();

            if ($generator === null || $languageConfig === null) {
                return 1;
            }

            $specPath = $this->resolveSpecPath();
            if ($specPath === null) {
                return 1;
            }

            $outputPath = $this->resolveOutputPath($language, $languageConfig);
            File::ensureDirectoryExists($outputPath);

            $packageName = $this->resolvePackageName();
            if ($packageName !== null && $generator === 'openapi-typescript') {
                $this->warn('--package-name is ignored when using openapi-typescript generator.');
            }

            $command = $this->commandBuilder->build(
                generator: $generator,
                language: $language,
                specPath: $specPath,
                outputPath: $outputPath,
                sdkConfig: $this->sdkConfig(),
                languageConfig: $languageConfig,
                packageName: $packageName
            );
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());
            $this->cleanupTemporarySpec();

            return 1;
        }

        $this->line("Generator: {$generator}");
        $this->line("Language: {$language}");
        $this->line("Spec: {$specPath}");
        $this->line("Output: {$outputPath}");
        $this->line('Command: '.$this->formatCommandForDisplay($command));

        if ((bool) $this->option('dry-run')) {
            $this->info('✅ Dry run completed.');
            $this->cleanupTemporarySpec();

            return 0;
        }

        try {
            $result = $this->commandRunner->run($command, base_path());
        } catch (Throwable $e) {
            $this->error('SDK generator process failed: '.$e->getMessage());
            $this->cleanupTemporarySpec();

            return 1;
        }

        if ($this->output->isVerbose() && trim($result->output) !== '') {
            $this->line(trim($result->output));
        }

        if (! $result->successful()) {
            if (trim($result->errorOutput) !== '') {
                $this->error(trim($result->errorOutput));
            } elseif (trim($result->output) !== '') {
                $this->error(trim($result->output));
            }

            $this->error("SDK generation failed with exit code {$result->exitCode}.");
            $this->line('Please ensure the generator CLI is installed and available in PATH.');
            $this->cleanupTemporarySpec();

            return 1;
        }

        $this->info("✅ SDK generated to: {$outputPath}");
        $this->cleanupTemporarySpec();

        return 0;
    }

    private function resolveLanguage(): string
    {
        return strtolower(trim((string) $this->argument('language')));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveLanguageConfig(string $language): ?array
    {
        $languages = $this->sdkLanguages();
        if (! isset($languages[$language])) {
            $this->error("Unsupported language: {$language}");
            $this->line('Supported languages: '.implode(', ', array_keys($languages)));

            return null;
        }

        $config = $languages[$language];
        if (! is_array($config)) {
            return [];
        }

        return $config;
    }

    private function resolveGenerator(): ?string
    {
        $configuredGenerator = $this->option('generator') ?: ($this->sdkConfig()['generator'] ?? self::DEFAULT_GENERATOR);
        $generator = strtolower(trim((string) $configuredGenerator));

        if (! in_array($generator, self::SUPPORTED_GENERATORS, true)) {
            $this->error("Unsupported generator: {$generator}");
            $this->line('Supported generators: '.implode(', ', self::SUPPORTED_GENERATORS));

            return null;
        }

        return $generator;
    }

    private function resolveSpecPath(): ?string
    {
        $specOption = trim((string) $this->option('spec'));
        if ($specOption !== '') {
            $specPath = $this->normalizePath($specOption);
            if (! File::exists($specPath)) {
                $this->error("OpenAPI specification file not found: {$specPath}");

                return null;
            }

            return $specPath;
        }

        $temporaryPath = storage_path('app/spectrum/sdk/openapi-sdk.json');
        File::ensureDirectoryExists(dirname($temporaryPath));

        $this->line('📝 No --spec provided. Generating OpenAPI spec...');
        $exitCode = $this->call('spectrum:generate', [
            '--format' => 'json',
            '--output' => $temporaryPath,
            '--no-cache' => true,
        ]);

        if ($exitCode !== 0 || ! File::exists($temporaryPath)) {
            $this->error('Failed to generate OpenAPI spec for SDK generation.');

            return null;
        }

        $this->temporarySpecPath = $temporaryPath;

        return $temporaryPath;
    }

    /**
     * @param  array<string, mixed>  $languageConfig
     */
    private function resolveOutputPath(string $language, array $languageConfig): string
    {
        $configuredOutput = $languageConfig['output'] ?? "sdk/{$language}";
        $outputOption = trim((string) $this->option('output'));

        if ($outputOption !== '') {
            return $this->normalizePath($outputOption);
        }

        if (! is_string($configuredOutput) || trim($configuredOutput) === '') {
            return $this->normalizePath("sdk/{$language}");
        }

        return $this->normalizePath($configuredOutput);
    }

    private function resolvePackageName(): ?string
    {
        $packageName = trim((string) $this->option('package-name'));

        return $packageName === '' ? null : $packageName;
    }

    private function cleanupTemporarySpec(): void
    {
        if ($this->temporarySpecPath === null) {
            return;
        }

        if (File::exists($this->temporarySpecPath)) {
            File::delete($this->temporarySpecPath);
        }

        $this->temporarySpecPath = null;
    }

    /**
     * @param  array<int, string>  $command
     */
    private function formatCommandForDisplay(array $command): string
    {
        $parts = array_map(static fn (string $part): string => escapeshellarg($part), $command);

        return implode(' ', $parts);
    }

    private function normalizePath(string $path): string
    {
        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return base_path($path);
    }

    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if (str_starts_with($path, '/')) {
            return true;
        }

        return preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function sdkConfig(): array
    {
        $config = config('spectrum.sdk', []);

        return is_array($config) ? $config : [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function sdkLanguages(): array
    {
        $languages = $this->sdkConfig()['languages'] ?? [];
        if (! is_array($languages)) {
            return [];
        }

        $normalized = [];
        foreach ($languages as $language => $config) {
            if (! is_string($language)) {
                continue;
            }

            $normalized[strtolower($language)] = is_array($config) ? $config : [];
        }

        return $normalized;
    }
}
