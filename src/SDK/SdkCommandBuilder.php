<?php

declare(strict_types=1);

namespace LaravelSpectrum\SDK;

use InvalidArgumentException;

final class SdkCommandBuilder
{
    /**
     * @param  array<string, mixed>  $sdkConfig
     * @param  array<string, mixed>  $languageConfig
     * @return array<int, string>
     */
    public function build(
        string $generator,
        string $language,
        string $specPath,
        string $outputPath,
        array $sdkConfig,
        array $languageConfig,
        ?string $packageName = null
    ): array {
        return match ($generator) {
            'openapi-generator' => $this->buildOpenApiGeneratorCommand(
                language: $language,
                specPath: $specPath,
                outputPath: $outputPath,
                sdkConfig: $sdkConfig,
                languageConfig: $languageConfig,
                packageName: $packageName
            ),
            'openapi-typescript' => $this->buildOpenApiTypescriptCommand(
                language: $language,
                specPath: $specPath,
                outputPath: $outputPath,
                sdkConfig: $sdkConfig
            ),
            'kiota' => $this->buildKiotaCommand(
                language: $language,
                specPath: $specPath,
                outputPath: $outputPath,
                sdkConfig: $sdkConfig,
                languageConfig: $languageConfig,
                packageName: $packageName
            ),
            default => throw new InvalidArgumentException("Unsupported SDK generator: {$generator}"),
        };
    }

    /**
     * @param  array<string, mixed>  $sdkConfig
     * @param  array<string, mixed>  $languageConfig
     * @return array<int, string>
     */
    private function buildOpenApiGeneratorCommand(
        string $language,
        string $specPath,
        string $outputPath,
        array $sdkConfig,
        array $languageConfig,
        ?string $packageName
    ): array {
        $command = [
            ...$this->resolveBinaryTokens($sdkConfig, 'openapi-generator', 'openapi-generator-cli'),
            'generate',
            '-i',
            $specPath,
            '-g',
            $this->resolveOpenApiGeneratorName($language, $languageConfig),
            '-o',
            $outputPath,
        ];

        $additionalProperties = $this->normalizeAdditionalProperties($languageConfig['additional_properties'] ?? []);
        if ($packageName !== null && trim($packageName) !== '') {
            $propertyName = $this->resolvePackageNameProperty($language, $languageConfig);
            $additionalProperties[$propertyName] = $packageName;
        }

        if ($additionalProperties !== []) {
            $command[] = '--additional-properties='.$this->formatAdditionalProperties($additionalProperties);
        }

        return [...$command, ...$this->normalizeAdditionalArgs($languageConfig['additional_args'] ?? [])];
    }

    /**
     * @param  array<string, mixed>  $sdkConfig
     * @return array<int, string>
     */
    private function buildOpenApiTypescriptCommand(
        string $language,
        string $specPath,
        string $outputPath,
        array $sdkConfig
    ): array {
        if ($language !== 'typescript') {
            throw new InvalidArgumentException('openapi-typescript generator only supports the typescript language.');
        }

        return [
            ...$this->resolveBinaryTokens($sdkConfig, 'openapi-typescript', 'npx openapi-typescript'),
            $specPath,
            '--output',
            rtrim($outputPath, '/').'/index.ts',
        ];
    }

    /**
     * @param  array<string, mixed>  $sdkConfig
     * @param  array<string, mixed>  $languageConfig
     * @return array<int, string>
     */
    private function buildKiotaCommand(
        string $language,
        string $specPath,
        string $outputPath,
        array $sdkConfig,
        array $languageConfig,
        ?string $packageName
    ): array {
        $kiotaLanguage = $languageConfig['kiota_language'] ?? $language;
        if (! is_string($kiotaLanguage) || trim($kiotaLanguage) === '') {
            $kiotaLanguage = $language;
        }

        $command = [
            ...$this->resolveBinaryTokens($sdkConfig, 'kiota', 'kiota'),
            'generate',
            '--openapi',
            $specPath,
            '--language',
            $kiotaLanguage,
            '--output',
            $outputPath,
        ];

        if ($packageName !== null && trim($packageName) !== '') {
            $command[] = '--namespace-name';
            $command[] = $packageName;
        }

        return [...$command, ...$this->normalizeAdditionalArgs($languageConfig['additional_args'] ?? [])];
    }

    /**
     * @param  array<string, mixed>  $sdkConfig
     * @return array<int, string>
     */
    private function resolveBinaryTokens(array $sdkConfig, string $key, string $default): array
    {
        $binaries = $sdkConfig['binaries'] ?? [];
        $binary = $default;

        if (is_array($binaries) && isset($binaries[$key]) && is_string($binaries[$key]) && trim($binaries[$key]) !== '') {
            $binary = trim($binaries[$key]);
        }

        $tokens = preg_split('/\s+/', $binary);
        if ($tokens === false) {
            throw new InvalidArgumentException("Invalid SDK binary configuration for {$key}.");
        }

        $tokens = array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));

        if ($tokens === []) {
            throw new InvalidArgumentException("SDK binary configuration for {$key} is empty.");
        }

        return $tokens;
    }

    /**
     * @param  array<string, mixed>  $languageConfig
     */
    private function resolveOpenApiGeneratorName(string $language, array $languageConfig): string
    {
        $configured = $languageConfig['generator_name'] ?? null;
        if (is_string($configured) && trim($configured) !== '') {
            return $configured;
        }

        return match ($language) {
            'typescript' => 'typescript-axios',
            'swift' => 'swift5',
            'kotlin' => 'kotlin',
            default => throw new InvalidArgumentException("No OpenAPI Generator preset found for language: {$language}"),
        };
    }

    /**
     * @param  array<string, mixed>  $languageConfig
     */
    private function resolvePackageNameProperty(string $language, array $languageConfig): string
    {
        $configured = $languageConfig['package_name_property'] ?? null;
        if (is_string($configured) && trim($configured) !== '') {
            return $configured;
        }

        return match ($language) {
            'typescript' => 'npmName',
            'swift' => 'projectName',
            default => 'packageName',
        };
    }

    /**
     * @return array<string, string>
     */
    private function normalizeAdditionalProperties(mixed $additionalProperties): array
    {
        if (! is_array($additionalProperties)) {
            return [];
        }

        $normalized = [];

        foreach ($additionalProperties as $key => $value) {
            if (! is_string($key) || trim($key) === '') {
                continue;
            }

            $normalized[$key] = $this->normalizeScalarValue($value);
        }

        return $normalized;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeAdditionalArgs(mixed $additionalArgs): array
    {
        if (! is_array($additionalArgs)) {
            return [];
        }

        $normalized = [];
        foreach ($additionalArgs as $arg) {
            if (! is_string($arg) || trim($arg) === '') {
                continue;
            }

            $normalized[] = $arg;
        }

        return $normalized;
    }

    /**
     * @param  array<string, string>  $additionalProperties
     */
    private function formatAdditionalProperties(array $additionalProperties): string
    {
        $pairs = [];

        foreach ($additionalProperties as $key => $value) {
            $pairs[] = $key.'='.$value;
        }

        return implode(',', $pairs);
    }

    private function normalizeScalarValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        $encoded = json_encode($value);

        return is_string($encoded) ? $encoded : '';
    }
}
