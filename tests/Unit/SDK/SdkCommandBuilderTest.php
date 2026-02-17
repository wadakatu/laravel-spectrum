<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\SDK;

use InvalidArgumentException;
use LaravelSpectrum\SDK\SdkCommandBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SdkCommandBuilderTest extends TestCase
{
    #[Test]
    public function it_builds_openapi_generator_command_with_additional_properties(): void
    {
        $builder = new SdkCommandBuilder;

        $command = $builder->build(
            generator: 'openapi-generator',
            language: 'typescript',
            specPath: '/tmp/openapi.json',
            outputPath: '/tmp/sdk/typescript',
            sdkConfig: [
                'binaries' => [
                    'openapi-generator' => 'custom-openapi-generator',
                ],
            ],
            languageConfig: [
                'generator_name' => 'typescript-axios',
                'package_name_property' => 'npmName',
                'additional_properties' => [
                    'supportsES6' => true,
                    'snapshot' => false,
                ],
            ],
            packageName: '@myapp/api-client'
        );

        $this->assertSame([
            'custom-openapi-generator',
            'generate',
            '-i',
            '/tmp/openapi.json',
            '-g',
            'typescript-axios',
            '-o',
            '/tmp/sdk/typescript',
            '--additional-properties=supportsES6=true,snapshot=false,npmName=@myapp/api-client',
        ], $command);
    }

    #[Test]
    public function it_builds_openapi_typescript_command(): void
    {
        $builder = new SdkCommandBuilder;

        $command = $builder->build(
            generator: 'openapi-typescript',
            language: 'typescript',
            specPath: '/tmp/openapi.json',
            outputPath: '/tmp/sdk/typescript',
            sdkConfig: [],
            languageConfig: [],
            packageName: null
        );

        $this->assertSame([
            'npx',
            'openapi-typescript',
            '/tmp/openapi.json',
            '--output',
            '/tmp/sdk/typescript/index.ts',
        ], $command);
    }

    #[Test]
    public function it_builds_kiota_command_with_custom_binary_and_namespace(): void
    {
        $builder = new SdkCommandBuilder;

        $command = $builder->build(
            generator: 'kiota',
            language: 'kotlin',
            specPath: '/tmp/openapi.json',
            outputPath: '/tmp/sdk/kotlin',
            sdkConfig: [
                'binaries' => [
                    'kiota' => 'dotnet tool run kiota',
                ],
            ],
            languageConfig: [
                'kiota_language' => 'java',
                'additional_args' => ['--clean-output'],
            ],
            packageName: 'com.example.sdk'
        );

        $this->assertSame([
            'dotnet',
            'tool',
            'run',
            'kiota',
            'generate',
            '--openapi',
            '/tmp/openapi.json',
            '--language',
            'java',
            '--output',
            '/tmp/sdk/kotlin',
            '--namespace-name',
            'com.example.sdk',
            '--clean-output',
        ], $command);
    }

    #[Test]
    public function it_falls_back_to_defaults_when_generator_name_or_package_property_are_blank(): void
    {
        $builder = new SdkCommandBuilder;

        $command = $builder->build(
            generator: 'openapi-generator',
            language: 'typescript',
            specPath: '/tmp/openapi.json',
            outputPath: '/tmp/sdk/typescript',
            sdkConfig: [],
            languageConfig: [
                'generator_name' => '   ',
                'package_name_property' => '   ',
                'additional_properties' => [],
            ],
            packageName: '@scope/name'
        );

        $this->assertSame('typescript-axios', $command[5] ?? null);
        $this->assertSame('--additional-properties=npmName=@scope/name', $command[8] ?? null);
    }

    #[Test]
    public function it_ignores_whitespace_only_package_name(): void
    {
        $builder = new SdkCommandBuilder;

        $command = $builder->build(
            generator: 'openapi-generator',
            language: 'kotlin',
            specPath: '/tmp/openapi.json',
            outputPath: '/tmp/sdk/kotlin',
            sdkConfig: [],
            languageConfig: [
                'additional_properties' => [
                    'library' => 'jvm-okhttp4',
                ],
            ],
            packageName: '   '
        );

        $this->assertSame('--additional-properties=library=jvm-okhttp4', $command[8] ?? null);
    }

    #[Test]
    public function it_normalizes_additional_properties_and_additional_args(): void
    {
        $builder = new SdkCommandBuilder;

        $command = $builder->build(
            generator: 'openapi-generator',
            language: 'kotlin',
            specPath: '/tmp/openapi.json',
            outputPath: '/tmp/sdk/kotlin',
            sdkConfig: [],
            languageConfig: [
                'additional_properties' => [
                    '   ' => 'ignored',
                    'supportsES6' => true,
                    'option' => ['a' => 1],
                ],
                'additional_args' => [
                    '--skip-validate-spec',
                    '   ',
                    123,
                    '--global-property=models',
                ],
            ],
            packageName: null
        );

        $this->assertSame('--additional-properties=supportsES6=true,option={"a":1}', $command[8] ?? null);
        $this->assertSame('--skip-validate-spec', $command[9] ?? null);
        $this->assertSame('--global-property=models', $command[10] ?? null);
        $this->assertCount(11, $command);
    }

    #[Test]
    public function it_uses_default_binary_when_configured_binary_is_whitespace_only(): void
    {
        $builder = new SdkCommandBuilder;

        $command = $builder->build(
            generator: 'openapi-typescript',
            language: 'typescript',
            specPath: '/tmp/openapi.json',
            outputPath: '/tmp/sdk/typescript/',
            sdkConfig: [
                'binaries' => [
                    'openapi-typescript' => '   ',
                ],
            ],
            languageConfig: [],
            packageName: null
        );

        $this->assertSame([
            'npx',
            'openapi-typescript',
            '/tmp/openapi.json',
            '--output',
            '/tmp/sdk/typescript/index.ts',
        ], $command);
    }

    #[Test]
    public function it_throws_for_unsupported_generator(): void
    {
        $builder = new SdkCommandBuilder;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported SDK generator');

        $builder->build(
            generator: 'unknown',
            language: 'typescript',
            specPath: '/tmp/openapi.json',
            outputPath: '/tmp/sdk/typescript',
            sdkConfig: [],
            languageConfig: [],
            packageName: null
        );
    }

    #[Test]
    public function it_throws_when_openapi_typescript_is_used_for_non_typescript_language(): void
    {
        $builder = new SdkCommandBuilder;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('only supports the typescript language');

        $builder->build(
            generator: 'openapi-typescript',
            language: 'swift',
            specPath: '/tmp/openapi.json',
            outputPath: '/tmp/sdk/swift',
            sdkConfig: [],
            languageConfig: [],
            packageName: null
        );
    }
}
