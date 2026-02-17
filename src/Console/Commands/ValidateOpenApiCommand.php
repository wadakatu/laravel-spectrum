<?php

declare(strict_types=1);

namespace LaravelSpectrum\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use LaravelSpectrum\Validation\OpenApiBestPracticeChecker;
use LaravelSpectrum\Validation\OpenApiRequirementValidator;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class ValidateOpenApiCommand extends Command
{
    protected $signature = 'spectrum:validate
                            {path? : Path to the OpenAPI specification file}
                            {--generate : Generate the OpenAPI spec before validation}
                            {--strict : Treat warnings as errors}
                            {--format=text : Output format (text|json|junit)}';

    protected $description = 'Validate an OpenAPI specification for structural compliance and best practices';

    public function handle(): int
    {
        $format = strtolower((string) $this->option('format'));
        if (! in_array($format, ['text', 'json', 'junit'], true)) {
            $this->error('Unsupported format. Use one of: text, json, junit.');

            return 1;
        }

        $strict = (bool) $this->option('strict');
        $isMachineOutput = $format !== 'text';

        $specPath = $this->resolveSpecPath();

        if ((bool) $this->option('generate')) {
            if (! $isMachineOutput) {
                $this->info('📝 Generating OpenAPI specification before validation...');
            }

            $generateExitCode = $this->runGenerateCommand($specPath);
            if ($generateExitCode !== 0) {
                return $this->renderFailure(
                    format: $format,
                    message: 'Failed to generate OpenAPI specification before validation.',
                    strict: $strict,
                    filePath: $specPath
                );
            }
        }

        if (! File::exists($specPath)) {
            return $this->renderFailure(
                format: $format,
                message: "OpenAPI specification file not found: {$specPath}",
                strict: $strict,
                filePath: $specPath
            );
        }

        $content = File::get($specPath);
        if (! is_string($content) || trim($content) === '') {
            return $this->renderFailure(
                format: $format,
                message: "OpenAPI specification file is empty: {$specPath}",
                strict: $strict,
                filePath: $specPath
            );
        }

        try {
            $spec = $this->parseSpec($content, $specPath);
        } catch (\Throwable $e) {
            return $this->renderFailure(
                format: $format,
                message: 'Failed to parse OpenAPI specification: '.$e->getMessage(),
                strict: $strict,
                filePath: $specPath
            );
        }

        $schemaValidation = $this->validateSchema($spec);
        $schemaWarnings = $schemaValidation['warnings'];
        $schemaErrors = $schemaValidation['errors'];

        $expectedVersion = $this->determineExpectedVersion($spec);

        $requirementValidator = new OpenApiRequirementValidator;
        $requirementReport = $requirementValidator->validate(
            spec: $spec,
            rawJson: json_encode($spec, JSON_THROW_ON_ERROR),
            expectedVersion: $expectedVersion,
            schemaValid: $schemaValidation['valid'],
            schemaErrors: $schemaErrors
        );

        $bestPracticeChecker = new OpenApiBestPracticeChecker;
        $warnings = array_values(array_unique(array_merge($bestPracticeChecker->check($spec), $schemaWarnings)));

        $errors = $requirementReport['failures'];

        $status = (count($errors) > 0 || ($strict && count($warnings) > 0)) ? 'FAIL' : 'PASS';

        $report = [
            'status' => $status,
            'strict' => $strict,
            'file' => $specPath,
            'openapi_version' => $spec['openapi'] ?? null,
            'summary' => [
                'errors' => count($errors),
                'warnings' => count($warnings),
                'checks' => $requirementReport['summary'],
            ],
            'errors' => $errors,
            'warnings' => $warnings,
        ];

        $this->renderReport($format, $report);

        return $status === 'PASS' ? 0 : 1;
    }

    private function resolveSpecPath(): string
    {
        $pathArgument = $this->argument('path');

        if (is_string($pathArgument) && trim($pathArgument) !== '') {
            return $pathArgument;
        }

        if (function_exists('storage_path')) {
            return storage_path('app/spectrum/openapi.json');
        }

        return getcwd().'/storage/spectrum/openapi.json';
    }

    private function runGenerateCommand(string $specPath): int
    {
        $format = $this->detectFormatFromPath($specPath);

        $options = [
            '--format' => $format,
            '--output' => $specPath,
        ];

        return $this->call('spectrum:generate', $options);
    }

    private function detectFormatFromPath(string $specPath): string
    {
        $lowerPath = strtolower($specPath);

        if (str_ends_with($lowerPath, '.yaml') || str_ends_with($lowerPath, '.yml')) {
            return 'yaml';
        }

        return 'json';
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array{valid: bool, errors: array<int, string>, warnings: array<int, string>}
     */
    private function validateSchema(array $spec): array
    {
        $schemaErrors = [];
        $schemaWarnings = [];

        if (! class_exists('cebe\\openapi\\Reader') || ! class_exists('cebe\\openapi\\spec\\OpenApi')) {
            return [
                'valid' => true,
                'errors' => [],
                'warnings' => ['Schema-level validation skipped because cebe/openapi is not installed.'],
            ];
        }

        try {
            /** @var class-string $readerClass */
            $readerClass = 'cebe\\openapi\\Reader';
            /** @var class-string $openApiClass */
            $openApiClass = 'cebe\\openapi\\spec\\OpenApi';

            $openApi = $readerClass::readFromJson((string) json_encode($spec, JSON_THROW_ON_ERROR));

            if (! $openApi instanceof $openApiClass) {
                $schemaErrors[] = 'Failed to parse the OpenAPI document with cebe/openapi.';

                return [
                    'valid' => false,
                    'errors' => $schemaErrors,
                    'warnings' => $schemaWarnings,
                ];
            }

            $isValid = $openApi->validate();
            if (! $isValid) {
                foreach ($openApi->getErrors() as $error) {
                    if (is_string($error) && trim($error) !== '') {
                        $schemaErrors[] = $error;
                    }
                }
            }

            return [
                'valid' => $isValid,
                'errors' => $schemaErrors,
                'warnings' => $schemaWarnings,
            ];
        } catch (\Throwable $e) {
            return [
                'valid' => false,
                'errors' => ['Schema validator exception: '.$e->getMessage()],
                'warnings' => $schemaWarnings,
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private function determineExpectedVersion(array $spec): string
    {
        $openApiVersion = $spec['openapi'] ?? null;

        if (is_string($openApiVersion) && trim($openApiVersion) !== '') {
            return $openApiVersion;
        }

        return '3.0.0';
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function renderReport(string $format, array $report): void
    {
        if ($format === 'json') {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return;
        }

        if ($format === 'junit') {
            $this->output->writeln($this->toJunitXml($report), OutputInterface::OUTPUT_RAW);

            return;
        }

        $errors = $report['errors'];
        $warnings = $report['warnings'];

        if ($errors === []) {
            $this->info('✅ OpenAPI structural validation passed');
        } else {
            $this->error('❌ OpenAPI structural validation failed');
            foreach ($errors as $error) {
                $this->line('  - '.$error);
            }
        }

        if ($warnings !== []) {
            $this->warn(sprintf('⚠️  %d warning(s) found:', count($warnings)));
            foreach ($warnings as $warning) {
                $this->line('  - '.$warning);
            }
        }

        $this->line('');
        $this->line(sprintf(
            'Summary: %s (%d errors, %d warnings)',
            $report['status'],
            $report['summary']['errors'],
            $report['summary']['warnings']
        ));
    }

    /**
     * @param  array{
     *   errors?: array<int, string>,
     *   warnings?: array<int, string>,
     *   strict?: bool
     * }  $report
     */
    private function toJunitXml(array $report): string
    {
        $errors = is_array($report['errors'] ?? null) ? $report['errors'] : [];
        $warnings = is_array($report['warnings'] ?? null) ? $report['warnings'] : [];
        $strict = (bool) ($report['strict'] ?? false);

        $tests = max(1, count($errors) + count($warnings));
        $failures = count($errors) + ($strict ? count($warnings) : 0);
        $skipped = $strict ? 0 : count($warnings);

        $xml = [];
        $xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml[] = sprintf(
            '<testsuite name="spectrum:validate" tests="%d" failures="%d" skipped="%d">',
            $tests,
            $failures,
            $skipped
        );

        if ($errors === [] && $warnings === []) {
            $xml[] = '  <testcase classname="OpenApiValidation" name="spec-compliance" />';
        }

        foreach ($errors as $index => $error) {
            $xml[] = sprintf('  <testcase classname="OpenApiValidation" name="error-%d">', $index + 1);
            $xml[] = sprintf('    <failure message="%s" />', $this->escapeXml((string) $error));
            $xml[] = '  </testcase>';
        }

        foreach ($warnings as $index => $warning) {
            $xml[] = sprintf('  <testcase classname="OpenApiValidation" name="warning-%d">', $index + 1);

            if ($strict) {
                $xml[] = sprintf('    <failure message="%s" />', $this->escapeXml((string) $warning));
            } else {
                $xml[] = sprintf('    <skipped message="%s" />', $this->escapeXml((string) $warning));
            }

            $xml[] = '  </testcase>';
        }

        $xml[] = '</testsuite>';

        return implode("\n", $xml);
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * @return array<string, mixed>
     */
    private function parseSpec(string $content, string $specPath): array
    {
        $lowerPath = strtolower($specPath);

        if (str_ends_with($lowerPath, '.yaml') || str_ends_with($lowerPath, '.yml')) {
            $parsed = Yaml::parse($content);

            if (! is_array($parsed)) {
                throw new \RuntimeException('YAML document must decode to an object.');
            }

            return $parsed;
        }

        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new \RuntimeException('JSON document must decode to an object.');
        }

        return $decoded;
    }

    private function renderFailure(string $format, string $message, bool $strict, string $filePath): int
    {
        $report = [
            'status' => 'FAIL',
            'strict' => $strict,
            'file' => $filePath,
            'summary' => [
                'errors' => 1,
                'warnings' => 0,
                'checks' => [
                    'total' => 0,
                    'passed' => 0,
                    'failed' => 1,
                    'skipped' => 0,
                ],
            ],
            'errors' => [$message],
            'warnings' => [],
        ];

        $this->renderReport($format, $report);

        return 1;
    }
}
