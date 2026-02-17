<?php

declare(strict_types=1);

namespace LaravelSpectrum\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use LaravelSpectrum\Diff\OpenApiDiffAnalyzer;
use Symfony\Component\Yaml\Yaml;

class DiffOpenApiCommand extends Command
{
    protected $signature = 'spectrum:diff
                            {from? : Baseline OpenAPI specification path}
                            {to? : Target OpenAPI specification path}
                            {--against= : Compare target spec against last snapshot, git ref, or spec file}
                            {--breaking-only : Show only breaking changes}
                            {--format=text : Output format (text|json)}';

    protected $description = 'Compare two OpenAPI specifications and detect breaking changes';

    public function handle(): int
    {
        $format = strtolower((string) $this->option('format'));
        if (! in_array($format, ['text', 'json'], true)) {
            return $this->renderFailure(
                format: $format,
                message: 'Unsupported format. Use one of: text, json.'
            );
        }

        try {
            $comparison = $this->resolveComparison();

            $fromSpec = $this->parseSpec(
                content: $comparison['from_content'],
                pathHint: $comparison['from_path_hint']
            );
            $toSpec = $this->parseSpec(
                content: $comparison['to_content'],
                pathHint: $comparison['to_path_hint']
            );
        } catch (\Throwable $e) {
            return $this->renderFailure(
                format: $format,
                message: $e->getMessage()
            );
        }

        $report = [
            'from' => $comparison['from_label'],
            'to' => $comparison['to_label'],
            'breaking_only' => (bool) $this->option('breaking-only'),
        ] + (new OpenApiDiffAnalyzer)->analyze($fromSpec, $toSpec);

        if ($report['breaking_only']) {
            $report = $this->applyBreakingOnly($report);
        }

        $this->renderReport($format, $report);
        $this->saveLastSnapshot($comparison['to_content']);

        return $report['summary']['breaking'] > 0 ? 1 : 0;
    }

    /**
     * @return array{
     *   from_content: string,
     *   to_content: string,
     *   from_label: string,
     *   to_label: string,
     *   from_path_hint: string|null,
     *   to_path_hint: string|null
     * }
     */
    private function resolveComparison(): array
    {
        $fromArgument = $this->normalizeString($this->argument('from'));
        $toArgument = $this->normalizeString($this->argument('to'));
        $against = $this->normalizeString($this->option('against'));

        if ($against !== null) {
            if ($toArgument !== null) {
                throw new \RuntimeException('When using --against, provide only one positional argument: [target-spec-path].');
            }

            $targetPath = $fromArgument ?? $this->defaultCurrentSpecPath();
            $targetSpec = $this->readSpecFromFile($targetPath);

            if ($against === 'last') {
                $baselineSpec = $this->readSpecFromFile($this->lastSnapshotPath());
            } elseif (File::exists($against)) {
                $baselineSpec = $this->readSpecFromFile($against);
            } else {
                $baselineSpec = $this->readSpecFromGitRef($against, $targetPath);
            }

            return [
                'from_content' => $baselineSpec['content'],
                'to_content' => $targetSpec['content'],
                'from_label' => $baselineSpec['label'],
                'to_label' => $targetSpec['label'],
                'from_path_hint' => $baselineSpec['path_hint'],
                'to_path_hint' => $targetSpec['path_hint'],
            ];
        }

        if ($fromArgument === null || $toArgument === null) {
            throw new \RuntimeException(
                'Provide [from] and [to] file paths, or use --against=last|<git-ref>|<file>.'
            );
        }

        $fromSpec = $this->readSpecFromFile($fromArgument);
        $toSpec = $this->readSpecFromFile($toArgument);

        return [
            'from_content' => $fromSpec['content'],
            'to_content' => $toSpec['content'],
            'from_label' => $fromSpec['label'],
            'to_label' => $toSpec['label'],
            'from_path_hint' => $fromSpec['path_hint'],
            'to_path_hint' => $toSpec['path_hint'],
        ];
    }

    /**
     * @return array{content: string, label: string, path_hint: string}
     */
    private function readSpecFromFile(string $path): array
    {
        if (! File::exists($path)) {
            throw new \RuntimeException("OpenAPI specification file not found: {$path}");
        }

        $content = File::get($path);
        if (! is_string($content) || trim($content) === '') {
            throw new \RuntimeException("OpenAPI specification file is empty: {$path}");
        }

        return [
            'content' => $content,
            'label' => $path,
            'path_hint' => $path,
        ];
    }

    /**
     * @return array{content: string, label: string, path_hint: string}
     */
    private function readSpecFromGitRef(string $ref, string $targetPath): array
    {
        $gitRoot = $this->resolveGitRoot();
        $relativeCandidates = $this->buildGitPathCandidates($targetPath, $gitRoot);

        if ($relativeCandidates === []) {
            throw new \RuntimeException(sprintf(
                "Unable to resolve a repository-relative path for '%s'.",
                $targetPath
            ));
        }

        $lastError = '';

        foreach ($relativeCandidates as $candidate) {
            $refSpec = $ref.':'.$candidate;
            $command = sprintf('git show %s 2>&1', escapeshellarg($refSpec));
            $outputLines = [];
            $exitCode = 0;
            exec($command, $outputLines, $exitCode);

            if ($exitCode === 0) {
                $content = implode("\n", $outputLines);
                if (trim($content) !== '') {
                    return [
                        'content' => $content,
                        'label' => $refSpec,
                        'path_hint' => $candidate,
                    ];
                }
            }

            $lastError = implode("\n", $outputLines);
        }

        $candidateList = implode(', ', $relativeCandidates);

        throw new \RuntimeException(sprintf(
            "Failed to load spec from git ref '%s'. Checked path(s): %s. %s",
            $ref,
            $candidateList,
            $lastError
        ));
    }

    private function resolveGitRoot(): string
    {
        $outputLines = [];
        $exitCode = 0;
        exec('git rev-parse --show-toplevel 2>&1', $outputLines, $exitCode);

        if ($exitCode !== 0) {
            throw new \RuntimeException('Unable to resolve git repository root for --against=<git-ref>.');
        }

        $gitRoot = trim(implode("\n", $outputLines));
        if ($gitRoot === '') {
            throw new \RuntimeException('Git repository root is empty.');
        }

        return $gitRoot;
    }

    /**
     * @return array<int, string>
     */
    private function buildGitPathCandidates(string $targetPath, string $gitRoot): array
    {
        $normalizedGitRoot = rtrim(str_replace('\\', '/', $gitRoot), '/');
        $normalizedTarget = str_replace('\\', '/', $targetPath);
        $candidates = [];

        if (str_starts_with($normalizedTarget, $normalizedGitRoot.'/')) {
            $candidates[] = substr($normalizedTarget, strlen($normalizedGitRoot) + 1);
        }

        $trimmed = ltrim($normalizedTarget, './');
        if ($trimmed !== '') {
            $candidates[] = $trimmed;
        }

        return array_values(array_unique($candidates));
    }

    private function defaultCurrentSpecPath(): string
    {
        if (function_exists('storage_path')) {
            return storage_path('app/spectrum/openapi.json');
        }

        return getcwd().'/storage/spectrum/openapi.json';
    }

    private function lastSnapshotPath(): string
    {
        if (function_exists('storage_path')) {
            return storage_path('app/spectrum/openapi-last.json');
        }

        return getcwd().'/storage/spectrum/openapi-last.json';
    }

    private function saveLastSnapshot(string $content): void
    {
        try {
            $snapshotPath = $this->lastSnapshotPath();
            File::ensureDirectoryExists(dirname($snapshotPath));
            File::put($snapshotPath, $content);
        } catch (\Throwable) {
            // Snapshot updates are best effort and should not fail the command.
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function parseSpec(string $content, ?string $pathHint = null): array
    {
        $hint = strtolower((string) $pathHint);
        $isYamlHint = str_ends_with($hint, '.yaml') || str_ends_with($hint, '.yml');

        if ($isYamlHint) {
            $parsedYaml = Yaml::parse($content);
            if (is_array($parsedYaml)) {
                return $parsedYaml;
            }

            throw new \RuntimeException('YAML document must decode to an object.');
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($decoded)) {
                return $decoded;
            }
        } catch (\Throwable) {
            // Try YAML next.
        }

        $parsedYaml = Yaml::parse($content);
        if (is_array($parsedYaml)) {
            return $parsedYaml;
        }

        throw new \RuntimeException('Specification must decode to a JSON/YAML object.');
    }

    /**
     * @param  array{
     *   from: string,
     *   to: string,
     *   breaking_only: bool,
     *   breaking_changes: array<int, array{type: string, operation: string, message: string}>,
     *   deprecations: array<int, array{type: string, operation: string, message: string}>,
     *   additions: array<int, array{type: string, operation: string, message: string}>,
     *   summary: array{breaking: int, deprecations: int, additions: int}
     * }  $report
     * @return array{
     *   from: string,
     *   to: string,
     *   breaking_only: bool,
     *   breaking_changes: array<int, array{type: string, operation: string, message: string}>,
     *   deprecations: array<int, array{type: string, operation: string, message: string}>,
     *   additions: array<int, array{type: string, operation: string, message: string}>,
     *   summary: array{breaking: int, deprecations: int, additions: int}
     * }
     */
    private function applyBreakingOnly(array $report): array
    {
        $report['deprecations'] = [];
        $report['additions'] = [];
        $report['summary']['deprecations'] = 0;
        $report['summary']['additions'] = 0;

        return $report;
    }

    /**
     * @param  array{
     *   from: string,
     *   to: string,
     *   breaking_only: bool,
     *   breaking_changes: array<int, array{type: string, operation: string, message: string}>,
     *   deprecations: array<int, array{type: string, operation: string, message: string}>,
     *   additions: array<int, array{type: string, operation: string, message: string}>,
     *   summary: array{breaking: int, deprecations: int, additions: int}
     * }  $report
     */
    private function renderReport(string $format, array $report): void
    {
        if ($format === 'json') {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return;
        }

        $this->line(sprintf('📊 API Diff: %s -> %s', $report['from'], $report['to']));
        $this->line('');

        if ($report['breaking_changes'] === []) {
            $this->info('✅ BREAKING CHANGES (0)');
        } else {
            $this->error(sprintf('❌ BREAKING CHANGES (%d):', count($report['breaking_changes'])));
            foreach ($report['breaking_changes'] as $breakingChange) {
                $this->line('  - '.$breakingChange['message']);
            }
        }

        if (! $report['breaking_only']) {
            if ($report['deprecations'] !== []) {
                $this->warn(sprintf('⚠️  DEPRECATIONS (%d):', count($report['deprecations'])));
                foreach ($report['deprecations'] as $deprecation) {
                    $this->line('  - '.$deprecation['message']);
                }
            }

            if ($report['additions'] !== []) {
                $this->info(sprintf('ℹ️  ADDITIONS (%d):', count($report['additions'])));
                foreach ($report['additions'] as $addition) {
                    $this->line('  - '.$addition['message']);
                }
            }
        }

        $this->line('');
        $this->line(sprintf(
            'Summary: %d breaking, %d deprecations, %d additions',
            $report['summary']['breaking'],
            $report['summary']['deprecations'],
            $report['summary']['additions']
        ));
    }

    private function renderFailure(string $format, string $message): int
    {
        $report = [
            'from' => null,
            'to' => null,
            'breaking_only' => (bool) $this->option('breaking-only'),
            'breaking_changes' => [
                [
                    'type' => 'command_error',
                    'operation' => '',
                    'message' => $message,
                ],
            ],
            'deprecations' => [],
            'additions' => [],
            'summary' => [
                'breaking' => 1,
                'deprecations' => 0,
                'additions' => 0,
            ],
        ];

        if ($format === 'json') {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return 1;
        }

        $this->error($message);

        return 1;
    }

    private function normalizeString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
